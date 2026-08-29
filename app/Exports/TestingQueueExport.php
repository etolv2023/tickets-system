<?php

namespace App\Exports;

use App\Exports\Concerns\CachesSheets;
use App\Exports\Concerns\ExportsDescriptions;
use App\Exports\Sheets\ArraySheet;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * F16 — the testing queue.
 *
 * Two tabs, because the screen has two lists that mean different things: what
 * this tester owns, and what is finished but assigned to no tester at all.
 * Merging them would hide the second, which is the whole reason it is surfaced.
 *
 * "My tickets to test" is role-based (2026-07-24): the tickets where I hold a
 * role flagged is_tester, not a fixed tester_id column.
 */
class TestingQueueExport implements WithMultipleSheets
{
    use Exportable, CachesSheets, ExportsDescriptions;

    public function __construct(private readonly User $tester)
    {
    }

    /** @return array<int, ArraySheet> */
    protected function buildSheets(): array
    {
        $testerRoleIds = Role::testerRoleIds();

        $sheets = [$this->mine($testerRoleIds)];

        // Same gate as the screen: the unassigned list is only shown to someone
        // who may see every ticket.
        if ($this->tester->hasPermission('tickets.view.all')) {
            $sheets[] = $this->unassigned($testerRoleIds);
        }

        return $sheets;
    }

    private function mine(mixed $testerRoleIds): ArraySheet
    {
        $rows = Ticket::query()
            ->select([
                'id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type',
                'priority', 'status', 'reported_at', 'sla_due_at',
            ])
            ->selectRaw($this->descriptionSelect('description'))
            ->with([
                'company:id,name', 'requester:id,name',
                // The developers to talk to — everyone who logged work on it.
                'workLogs.user:id,name',
            ])
            ->whereHas('roleAssignments', fn ($q) => $q
                ->whereIn('role_id', $testerRoleIds)
                ->where('user_id', $this->tester->id))
            ->whereIn('status', ['dev_done', 'testing'])
            ->defaultOrder()
            ->get();

        return new ArraySheet(
            'طابوري',
            ['رقم التذكرة', 'العنوان', 'الجهة الطالبة', 'النوع', 'الأولوية', 'الحالة', 'اللي اشتغل عليها', 'تاريخ الفتح', 'مهلة SLA', 'الوصف'],
            $rows->map(fn ($t) => $this->row($t, [
                $t->workLogs->map(fn ($w) => $w->user?->name)->filter()->unique()->implode('، '),
            ]))->all(),
        );
    }

    private function unassigned(mixed $testerRoleIds): ArraySheet
    {
        $rows = Ticket::query()
            ->select(['id', 'ticket_number', 'company_id', 'requested_by', 'title', 'type', 'priority', 'status', 'reported_at', 'sla_due_at'])
            ->selectRaw($this->descriptionSelect('description'))
            ->with('company:id,name', 'requester:id,name')
            ->where('status', 'dev_done')
            ->whereDoesntHave('roleAssignments', fn ($q) => $q->whereIn('role_id', $testerRoleIds))
            ->defaultOrder()
            ->limit(25)
            ->get();

        return new ArraySheet(
            'من غير تيستر',
            ['رقم التذكرة', 'العنوان', 'الجهة الطالبة', 'النوع', 'الأولوية', 'الحالة', 'تاريخ الفتح', 'مهلة SLA', 'الوصف'],
            $rows->map(fn ($t) => $this->row($t))->all(),
        );
    }

    /** @param array<int, mixed> $extra columns that sit between the status and the dates */
    private function row(Ticket $t, array $extra = []): array
    {
        $tz = config('app.display_timezone');

        return array_merge(
            [$t->ticket_number, $t->title, $t->originLabel(), $t->type->label(), $t->priority->label(), $t->status->label()],
            $extra,
            [
                $t->reported_at?->timezone($tz)->format('Y-m-d H:i'),
                $t->sla_due_at?->timezone($tz)->format('Y-m-d H:i'),
                $this->plainDescription($t->description_excerpt),
            ],
        );
    }
}
