<?php

namespace App\Console\Commands;

use App\Enums\PointSide;
use App\Enums\SubtaskSide;
use App\Enums\SubtaskStatus;
use App\Models\PointTransaction;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use App\Models\User;
use App\Services\PointEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * F18 fix (2026-07-21): points_awarded_at used to gate the whole ticket, so any
 * subtask finished after a ticket's first resolve (a reopened ticket, a devops
 * subtask added late) never got paid — silently, forever. PointEngineService
 * now checks per-subtask instead; this command pays out what the old gate
 * already lost. Reuses PointEngineService::awardSubtask() so the calculation
 * is identical to the live engine — nothing here is recomputed by hand.
 *
 * Also backfills PointEngineService::awardDevopsParticipation() — a second,
 * separate gap: devops assigned to an already-resolved ticket with no
 * dedicated subtask never earned the flat participation credit either,
 * since that logic didn't exist until now.
 *
 * Naturally idempotent: both stages only ever select what has no matching
 * point_transaction row yet, so running the command twice finds nothing to
 * do the second time.
 */
class BackfillPoints extends Command
{
    protected $signature = 'points:backfill';

    protected $description = 'يصرف النقاط الفايتة على صب تاسكس اتخلصت قبل كده ومكسبتش نقاط، وعلى ديف أوبس متعيّن من غير صب تاسك';

    public function handle(PointEngineService $engine): int
    {
        $subtaskCount = $this->backfillSubtasks($engine);
        $this->newLine();
        $devopsCount = $this->backfillDevopsParticipation($engine);

        if ($subtaskCount === 0 && $devopsCount === 0) {
            $this->info('مفيش نقاط فايتة نلاقيها.');
        }

        return self::SUCCESS;
    }

    private function backfillSubtasks(PointEngineService $engine): int
    {
        $subtasks = TicketSubtask::query()
            ->whereNull('deleted_at')
            ->where('status', SubtaskStatus::Done->value)
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

    private function backfillDevopsParticipation(PointEngineService $engine): int
    {
        $tickets = Ticket::query()
            ->whereNotNull('devops_id')
            ->whereNotNull('resolved_at')
            ->whereDoesntHave('pointTransactions', fn ($q) => $q
                ->where('side', PointSide::Devops->value)
                ->whereNull('subtask_id'))
            ->whereDoesntHave('subtasks', fn ($q) => $q
                ->whereNull('deleted_at')
                ->where('side', SubtaskSide::Devops->value)
                ->whereColumn('assignee_id', 'tickets.devops_id'))
            ->get()
            ->filter(fn (Ticket $ticket) => ! (
                $ticket->type->needsApproval() && $ticket->approval_status !== 'approved'
            ));

        if ($tickets->isEmpty()) {
            $this->info('مفيش تذاكر ديف أوبس فايتة مشاركتها.');

            return 0;
        }

        $ids = $tickets->pluck('id');

        foreach ($tickets as $ticket) {
            $period = ($ticket->resolved_at ?? now())->format('Y-m');

            DB::transaction(function () use ($engine, $ticket, $period) {
                $locked = Ticket::whereKey($ticket->id)->lockForUpdate()->first();
                $engine->awardDevopsParticipation($locked, $period);
            });
        }

        $created = PointTransaction::where('side', PointSide::Devops->value)
            ->whereNull('subtask_id')
            ->whereIn('ticket_id', $ids)
            ->get();

        $this->line('مشاركة ديف أوبس من غير صب تاسك:');
        $this->table(
            ['المستخدم', 'عدد التذاكر', 'إجمالي النقاط'],
            $created->groupBy('user_id')->map(fn ($rows, $userId) => [
                User::find($userId)?->name ?? "#{$userId}",
                $rows->count(),
                (float) $rows->sum('points'),
            ])->values()->all()
        );

        $this->info("اتصرف {$created->count()} من {$ids->count()} تذكرة — إجمالي {$created->sum('points')} نقطة.");

        return $created->count();
    }
}
