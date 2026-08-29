<?php

namespace App\Services;

use App\Models\ImpersonationSession;
use App\Models\User;
use RuntimeException;

/**
 * ★ (2026-08-29) Borrowing somebody's face, and writing down that you did — F29.
 *
 * Impersonation here is FULL by explicit request: while it is running you can do
 * anything the other person can do, and every action is recorded under THEIR
 * name. That is not a leak, it is the point — a work log finished as «مبرمج
 * باك» has to read as finished by مبرمج باك on every screen, or the screens
 * start lying about the work.
 *
 * What it costs is the answer to "who was actually there", and
 * impersonation_sessions is where that answer is kept instead.
 *
 * Takes and returns data; the session cookie is the controller's business, not
 * this class's (CLAUDE.md § 3).
 */
class ImpersonationService
{
    /**
     * Start one, or explain why not.
     *
     * @throws RuntimeException with a message meant for the user
     */
    public function start(User $actor, User $target, ?string $ip, ?string $userAgent): ImpersonationSession
    {
        $this->assertAllowed($actor, $target);

        return ImpersonationSession::create([
            'impersonator_id' => $actor->id,
            'impersonated_id' => $target->id,
            'started_at' => now(),
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 255),
        ]);
    }

    /**
     * Close it out. Idempotent: a session that is already ended keeps its
     * original end time, because the first one is the true one.
     */
    public function stop(ImpersonationSession $session): void
    {
        if ($session->isOpen()) {
            $session->forceFill(['ended_at' => now()])->save();
        }
    }

    /**
     * The four rules, and every one of them is load-bearing.
     *
     * @throws RuntimeException
     */
    private function assertAllowed(User $actor, User $target): void
    {
        if (! $actor->hasPermission('users.impersonate')) {
            throw new RuntimeException('مالكش صلاحية الدخول بعين حد تاني.');
        }

        if ($actor->id === $target->id) {
            throw new RuntimeException('ده حسابك انت.');
        }

        if (! $target->is_active) {
            throw new RuntimeException('الحساب ده موقوف.');
        }

        /*
         * THE ONE THAT MATTERS. Without it, A impersonates B, and if B also
         * holds this permission then from inside B's session A reaches C — and
         * the impersonation log records B → C, not A → C. Identity laundering,
         * and a straight route to any account.
         *
         * Because RoleSeeder gives the admin role every permission, the
         * practical effect is that an administrator cannot impersonate another
         * administrator. That is the correct answer, not a side effect.
         *
         * The second half of the same guard lives in the controller: while a
         * session is running, starting another is refused outright.
         */
        if ($target->hasPermission('users.impersonate')) {
            throw new RuntimeException(
                'مينفعش تدخل بعين حد معاه نفس الصلاحية دي — ده كان هيخليك توصل لأي حساب من خلاله.'
            );
        }
    }
}
