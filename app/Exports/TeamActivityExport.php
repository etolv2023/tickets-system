<?php

namespace App\Exports;

use App\Exports\Concerns\CachesSheets;
use App\Exports\Sheets\SubtaskRowsSheet;
use App\Exports\Sheets\TicketRowsSheet;
use App\Support\TeamActivityFilters;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * F19.3 — /reports/team-activity as a workbook.
 *
 * The screen's `show` control decides which halves are on screen; the file
 * follows it, so exporting a subtasks-only view does not hand back a tab of
 * tickets nobody asked for.
 */
class TeamActivityExport implements WithMultipleSheets
{
    use Exportable, CachesSheets;

    /** @param array<string, mixed> $filters the raw query string */
    public function __construct(
        private readonly array $filters,
        private readonly string $show = 'both',
    ) {
    }

    /** @return array<int, object> */
    protected function buildSheets(): array
    {
        $sheets = [];

        if ($this->show !== 'subtasks') {
            $sheets[] = new TicketRowsSheet(TeamActivityFilters::forTickets($this->filters));
        }

        if ($this->show !== 'tickets') {
            $sheets[] = new SubtaskRowsSheet(TeamActivityFilters::forSubtasks($this->filters));
        }

        return $sheets;
    }
}
