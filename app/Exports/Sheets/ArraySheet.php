<?php

namespace App\Exports\Sheets;

use App\Exports\Concerns\SanitizesCells;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One in-memory tab: a title, a heading row, and rows the caller already shaped.
 *
 * The multi-sheet exports are all the same shape — a handful of aggregate
 * tables that each need a tab — so the tab is a value rather than twenty
 * near-identical classes.
 *
 * In-memory on purpose: everything routed through here is an aggregate or a
 * capped result set. Anything that grows with the ticket table uses FromQuery
 * instead, so it chunks.
 *
 * WithStrictNullComparison is not decoration. PhpSpreadsheet's fromArray()
 * skips every value loosely equal to null, and in PHP `0 == null` is true — so
 * without it each zero came out as an empty cell. On a payroll sheet "0" and
 * "blank" are different claims, and the blank one reads as a broken export.
 */
class ArraySheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use SanitizesCells;

    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private readonly string $title,
        private readonly array $headings,
        private readonly iterable $rows,
    ) {
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        $out = [];

        foreach ($this->rows as $row) {
            $out[] = $this->sanitizeRow($row);
        }

        return $out;
    }

    /**
     * Excel refuses a tab name over 31 characters or containing : \ / ? * [ ],
     * and kills the whole file rather than trimming it — so the trim happens
     * here, once, instead of in every caller.
     */
    public function title(): string
    {
        return mb_substr(str_replace([':', chr(92), '/', '?', '*', '[', ']'], ' ', $this->title), 0, 31);
    }
}
