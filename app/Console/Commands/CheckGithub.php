<?php

namespace App\Console\Commands;

use App\Models\GithubRepository;
use App\Services\GitHubClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * F27 — is the connection healthy, and is the token no wider than it should be?
 *
 * The second half is the point. This integration is read-only by construction —
 * GitHubClient has no method that issues anything but GET — but that guarantee
 * lives in this codebase, and the token lives on GitHub. If somebody issues a
 * token with Contents: Read AND WRITE, nothing breaks and nothing complains;
 * the system just quietly holds a credential that could delete a branch.
 *
 * So this command asks GitHub what the token is actually allowed to do and says
 * so out loud. `push` or `admin` coming back true is not a failure of anything
 * here — it is a token that should be reissued narrower.
 */
class CheckGithub extends Command
{
    protected $signature = 'github:check';

    protected $description = 'فحص الاتصال بـ GitHub وصلاحيات رمز الوصول';

    public function handle(GitHubClient $github): int
    {
        $this->line('');

        if (! config('github.enabled')) {
            $this->warn('GITHUB_ENABLED = false — التكامل معطّل.');
        }

        if (blank(config('github.token'))) {
            $this->error('GITHUB_TOKEN فارغ. لا يوجد ما يمكن فحصه.');

            return self::FAILURE;
        }

        $problems = 0;

        // ── 1. Does the token work at all, and whose is it? ──────────────────
        try {
            $viewer = $github->viewer();

            if ($viewer === null) {
                $this->error('رفض GitHub رمز الوصول (401) — انتهت صلاحيته أو أُلغي.');

                return self::FAILURE;
            }

            $this->info('رمز الوصول يعمل — الحساب: ' . ($viewer['login'] ?? '؟'));
        } catch (Throwable $e) {
            $this->error('فشل الاتصال: ' . $e->getMessage());

            return self::FAILURE;
        }

        // ── 2. When does it stop working? ────────────────────────────────────
        $expiry = $github->tokenExpiresAt();

        if ($expiry === null) {
            $this->line('  تاريخ الانتهاء: لم يحدده GitHub (رمز بلا انتهاء، أو GitHub App).');
        } else {
            // Signed on purpose: a negative count is an expired token, and that
            // has to read differently from "expires soon".
            $days = (int) now()->diffInDays($expiry);
            $warnAt = (int) config('github.expiry_warning_days', 30);

            if ($days < 0) {
                $this->error('  انتهت صلاحية رمز الوصول منذ ' . abs($days) . ' يوم.');
                $problems++;
            } elseif ($days <= $warnAt) {
                $this->warn('  تنتهي صلاحية رمز الوصول خلال ' . $days . ' يوم (' . $expiry->toDateString() . ').');
                $problems++;
            } else {
                $this->line('  ينتهي في ' . $expiry->toDateString() . ' (' . $days . ' يوم).');
            }
        }

        // ── 3. Every repository: reachable, and read-only? ───────────────────
        $this->line('');

        foreach (GithubRepository::orderBy('position')->get() as $repository) {
            $label = $repository->fullName() . ($repository->is_active ? '' : ' (معطّل)');

            try {
                $data = $github->repository($repository);
            } catch (Throwable $e) {
                $this->error('✗ ' . $label . ' — ' . $e->getMessage());
                $problems++;

                continue;
            }

            if ($data === null) {
                $this->error('✗ ' . $label . ' — رمز الوصول لا يرى هذا المستودع. أضفه إلى قائمة المستودعات في الرمز.');
                $problems++;

                continue;
            }

            $this->info('✓ ' . $label . ' — الفرع الافتراضي: ' . ($data['default_branch'] ?? '؟'));

            // GitHub reports what this token may do to this repository. `pull`
            // is all that is wanted; anything above it is more authority than
            // this system has any code to use.
            $permissions = $data['permissions'] ?? null;

            if (! is_array($permissions)) {
                $this->line('    (لم يُرجع GitHub تفاصيل صلاحيات هذا الرمز.)');

                continue;
            }

            $excess = array_keys(array_filter([
                'admin' => $permissions['admin'] ?? false,
                'maintain' => $permissions['maintain'] ?? false,
                'push (كتابة)' => $permissions['push'] ?? false,
            ]));

            if ($excess !== []) {
                $this->warn('    ⚠ رمز الوصول يملك صلاحيات أوسع من المطلوب: ' . implode('، ', $excess) . '.');
                $this->warn('      هذا النظام لا يكتب أي شيء. أنشئ رمز الوصول بصلاحيتي Contents: Read-only وPull requests: Read-only.');
                $problems++;
            } else {
                $this->line('    الصلاحيات: قراءة فقط ✔');
            }

            if ($repository->last_sync_error) {
                $this->warn('    فشلت آخر مزامنة: ' . $repository->last_sync_error);
                $problems++;
            }
        }

        $this->line('');
        $this->line($problems === 0 ? 'كل شيء سليم.' : $problems . ' عناصر تحتاج إلى مراجعة.');

        return $problems === 0 ? self::SUCCESS : self::FAILURE;
    }
}
