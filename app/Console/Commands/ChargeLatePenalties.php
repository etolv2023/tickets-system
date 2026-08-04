<?php

namespace App\Console\Commands;

use App\Services\LatePenaltyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * ★ (2026-08-05) The 06:00 sweep: dock every subtask that is past its due date.
 *
 * Scheduled rather than event-driven on purpose. Nothing happens when a due
 * date passes — no save, no request, no status change; the subtask simply sits
 * there and becomes late, and there is no moment for a listener to hang off.
 * Waiting for the ticket to be resolved instead would mean a ticket open for
 * three weeks tells nobody they are losing points until payout day, which is
 * exactly when it is too late to act on.
 *
 * Six in the morning is chosen so the ledger is settled before anyone starts
 * work: whoever opens the system finds yesterday already accounted for, and
 * nobody is docked for a day they were still in the middle of.
 *
 * Safe to run by hand, and safe to run twice — UNIQUE(subtask_id, charge_key)
 * makes a repeat on the same day a no-op rather than a second deduction.
 * --as-of exists for exactly one reason: to replay a morning the scheduler
 * missed (a server that was down) at the date it should have run, so the charge
 * lands in the right day and the right month.
 */
class ChargeLatePenalties extends Command
{
    protected $signature = 'points:charge-late
                            {--as-of= : اليوم الي الفحص يتحسب بيه (YYYY-MM-DD)، الافتراضي النهاردة}
                            {--dry-run : اعرض الي هيتخصم من غير ما تكتب حاجة}';

    protected $description = 'خصم نقاط الصب تاسكس المتأخرة عن تاريخ استحقاقها';

    public function handle(LatePenaltyService $penalties): int
    {
        $asOf = $this->option('as-of')
            ? Carbon::parse($this->option('as-of'))
            : now();

        if ($this->option('dry-run')) {
            return $this->preview($penalties, $asOf);
        }

        $result = $penalties->chargeOverdue($asOf);

        $this->info(sprintf(
            'فحص %s — اتخصم: %d · اتعدّى: %d · تراكم التأخير: %s',
            $asOf->toDateString(),
            $result['charged'],
            $result['skipped'],
            $result['accumulating'] ? 'مفعّل' : 'مقفول'
        ));

        return self::SUCCESS;
    }

    /**
     * Deliberately routed through the same service, with writes turned off by
     * the caller rather than by a flag inside the rule: a preview that runs
     * different code from the real thing is a preview of nothing.
     */
    private function preview(LatePenaltyService $penalties, Carbon $asOf): int
    {
        $rows = $penalties->previewOverdue($asOf);

        if ($rows->isEmpty()) {
            $this->info("فحص {$asOf->toDateString()} — مفيش صب تاسك متأخرة تستاهل خصم.");

            return self::SUCCESS;
        }

        $this->table(
            ['الصب تاسك', 'التذكرة', 'الموظف', 'الاستحقاق', 'النقط', 'خُصم قبل كده؟'],
            $rows->map(fn ($r) => [
                mb_substr($r['title'], 0, 40),
                $r['ticket'],
                $r['assignee'],
                $r['due'],
                '-' . $r['points'],
                $r['charged_before'] ? 'أيوه' : 'لأ',
            ])->all()
        );

        $this->info(sprintf(
            'إجمالي هيتخصم: %s نقطة على %d صب تاسك · تراكم التأخير: %s',
            $rows->sum('points'),
            $rows->count(),
            $penalties->accumulates() ? 'مفعّل' : 'مقفول'
        ));

        return self::SUCCESS;
    }
}
