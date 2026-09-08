<?php

namespace App\Console\Commands;

use App\Models\PointTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ★ (2026-09-08) Who was docked for work that had already been cancelled.
 *
 * Until today LatePenaltyService decided "is this ticket still alive?" by
 * asking `resolved_at IS NULL`. A REJECTED ticket is never resolved, so that
 * column stayed NULL on it forever and its abandoned subtasks never left the
 * late-penalty net — they were docked once a day, every day, indefinitely.
 *
 * The fix stops the bleeding. It cannot undo it: the ledger is append-only
 * (PointTransaction::booted() refuses every update and delete) and
 * PointCorrectionService::assertCancellable() refuses anything that is not a
 * manual 'correction', so a wrong penalty cannot even be cancelled from the
 * corrections screen. Repairing it is a decision about people's pay, so this
 * command only ever ANSWERS the question — who, and how much — and leaves the
 * decision to a human. It writes nothing.
 *
 * WHAT COUNTS AS WRONGLY CHARGED, precisely:
 *
 *   1. type = 'penalty'
 *   2. on a ticket with resolved_at IS NULL and a status that is not open
 *   3. created AT OR AFTER the moment the ticket entered that status
 *
 * (2) is the exact blast radius: the old filter already excluded everything
 * with a resolved_at, so the only rows it let through were on tickets that died
 * without being resolved. (3) is what keeps the answer honest — a penalty
 * charged while the ticket was still open was legitimate, and stays legitimate
 * after the ticket is later rejected. Only the ones charged after the lights
 * went out are the bug's.
 */
class AuditDeadTicketPenalties extends Command
{
    protected $signature = 'points:audit-dead-penalties {--detail : اعرض كل سطر لوحده مش مجمّع بالشخص}';

    protected $description = 'مين اتخصم منه نقط على تذاكر مرفوضة أو ميتة (قراءة بس، مش بيكتب حاجة)';

    public function handle(): int
    {
        $rows = $this->wronglyCharged();

        if ($rows->isEmpty()) {
            $this->info('مفيش أي خصم غلط على تذاكر ميتة. الدفتر نضيف.');

            return self::SUCCESS;
        }

        $this->option('detail') ? $this->detail($rows) : $this->summary($rows);

        $this->newLine();
        $this->warn(sprintf(
            'الإجمالي: %d سطر · %s نقطة · %d شخص · %d تذكرة',
            $rows->count(),
            $this->points($rows->sum('points')),
            $rows->pluck('user_id')->unique()->count(),
            $rows->pluck('ticket_number')->unique()->count(),
        ));
        $this->line('الدفتر متلمسش. لو هتعوّض حد، ده بيتعمل بسطر تصحيح يدوي جديد.');

        return self::SUCCESS;
    }

    /** Grouped by person — the "who was wronged, and by how much" answer. */
    private function summary($rows): void
    {
        $this->table(
            ['الشخص', 'سطور', 'النقاط', 'التذاكر'],
            $rows->groupBy('user_id')
                ->map(fn ($group) => [
                    $group->first()->user_name ?? "#{$group->first()->user_id}",
                    $group->count(),
                    $this->points($group->sum('points')),
                    $group->pluck('ticket_number')->unique()->implode('، '),
                ])
                // Worst hit first: this list exists to be acted on from the top.
                ->sortBy(fn ($row) => (float) $row[2])
                ->values()
                ->all()
        );
    }

    private function detail($rows): void
    {
        $this->table(
            ['الشخص', 'التذكرة', 'حالتها', 'ماتت في', 'الخصم', 'النقاط', 'السبب'],
            $rows->map(fn ($r) => [
                $r->user_name ?? "#{$r->user_id}",
                $r->ticket_number,
                $r->status,
                (string) $r->died_at,
                (string) $r->created_at,
                $this->points($r->points),
                \Illuminate\Support\Str::limit((string) $r->reason, 40),
            ])->all()
        );
    }

    /** Always signed, so a column of deductions never reads as a column of awards. */
    private function points(float|string $points): string
    {
        return number_format((float) $points, 2);
    }

    /**
     * The three conditions from the class docblock, as one query.
     *
     * whereRaw carries no user input — it is two column comparisons against a
     * correlated aggregate, which the query builder has no vocabulary for. The
     * COALESCE fallback covers a ticket whose history row is missing (imported,
     * or written before status history existed): with no recorded moment of
     * death, every penalty on it is counted, which errs toward showing a row
     * for a human to judge rather than silently hiding one.
     */
    private function wronglyCharged(): \Illuminate\Support\Collection
    {
        return PointTransaction::query()
            ->from('point_transactions as pt')
            ->join('tickets as t', 't.id', '=', 'pt.ticket_id')
            ->leftJoin('users as u', 'u.id', '=', 'pt.user_id')
            ->select([
                'pt.id', 'pt.user_id', 'pt.points', 'pt.reason', 'pt.created_at',
                'u.name as user_name', 't.ticket_number', 't.status',
                DB::raw('(SELECT MAX(h.created_at) FROM ticket_status_history h'
                    . ' WHERE h.ticket_id = t.id AND h.to_status = t.status) as died_at'),
            ])
            ->where('pt.type', 'penalty')
            ->whereNull('t.resolved_at')
            ->whereNotIn('t.status', fn ($q) => $q
                ->select('key')->from('ticket_statuses')->where('is_open', true))
            ->whereRaw('pt.created_at >= COALESCE((SELECT MAX(h.created_at) FROM ticket_status_history h'
                . ' WHERE h.ticket_id = t.id AND h.to_status = t.status), t.created_at)')
            ->orderBy('pt.user_id')
            ->orderBy('pt.created_at')
            ->get();
    }
}
