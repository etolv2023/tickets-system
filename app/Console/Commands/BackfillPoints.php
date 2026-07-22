<?php

namespace App\Console\Commands;

use App\Models\PointTransaction;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\User;
use App\Services\PointEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F18 fix (2026-07-21): points_awarded_at used to gate the whole ticket, so any
 * subtask finished after a ticket's first resolve (a reopened ticket, a subtask
 * added late) never got paid — silently, forever. PointEngineService now checks
 * per-subtask instead; this command pays out what the old gate already lost.
 * Reuses PointEngineService::awardSubtask() so the calculation is identical to
 * the live engine — nothing here is recomputed by hand.
 *
 * Naturally idempotent: it only ever selects subtasks with no matching
 * point_transaction row yet, so running the command twice finds nothing to do
 * the second time.
 */
class BackfillPoints extends Command
{
    protected $signature = 'points:backfill';

    protected $description = 'يصرف النقاط الفايتة على صب تاسكس اتخلصت قبل كده ومكسبتش نقاط';

    public function handle(PointEngineService $engine): int
    {
        $subtaskCount = $this->backfillSubtasks($engine);

        if ($subtaskCount === 0) {
            $this->info('مفيش نقاط فايتة نلاقيها.');
        }

        return self::SUCCESS;
    }

    private function backfillSubtasks(PointEngineService $engine): int
    {
        $subtasks = TicketSubtask::query()
            ->whereNull('deleted_at')
            ->where('status', 'done')
            ->whereNotNull('assignee_id')
            ->where('points', '>', 0)
            ->whereDoesntHave('pointTransaction')
            ->whereHas('ticket', fn ($q) => $q->whereNotNull('resolved_at'))
            // F06 role-assignment extension: awardSubtask() reads
            // ->role->name_ar for a role-based subtask.
            ->with('ticket', 'role:id,name_ar')
            ->get()
            ->filter(fn (TicketSubtask $subtask) => ! (
                $subtask->ticket->type->needsApproval() && $subtask->ticket->approval_status !== 'approved'
            ));

        if ($subtasks->isEmpty()) {
            $this->info('مفيش صب تاسكس فايتة نقاطها.');

            return 0;
        }

        $ids = $subtasks->pluck('id');

        foreach ($subtasks as $subtask) {
            $ticket = $subtask->ticket;
            $period = ($ticket->resolved_at ?? now())->format('Y-m');

            DB::transaction(function () use ($engine, $ticket, $subtask, $period) {
                $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();
                $engine->awardSubtask($locked, $subtask, $period);
            });
        }

        $created = PointTransaction::whereIn('subtask_id', $ids)->get();

        $this->line('صب تاسكس:');
        $this->table(
            ['المستخدم', 'عدد الصب تاسكس', 'إجمالي النقاط'],
            $created->groupBy('user_id')->map(fn ($rows, $userId) => [
                User::find($userId)?->name ?? "#{$userId}",
                $rows->count(),
                (float) $rows->sum('points'),
            ])->values()->all()
        );

        $this->info("اتصرف {$created->count()} من {$ids->count()} صب تاسك — إجمالي {$created->sum('points')} نقطة.");

        if ($created->count() < $ids->count()) {
            $skipped = $ids->count() - $created->count();
            $this->warn("{$skipped} صب تاسك اترفض (side = other مالوش نقطة، أو Race) — التفاصيل في اللوج.");
        }

        return $created->count();
    }
}
