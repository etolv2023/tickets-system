<?php

namespace App\Exports\Concerns;

/**
 * CSV / formula injection guard (CLAUDE.md § 5, F02).
 *
 * A cell whose text starts with = + - @ or a control character is executed by
 * Excel and LibreOffice when the file is opened — on the recipient's machine,
 * with their privileges. `=cmd|'/c calc'!A1` is the classic proof.
 *
 * Reading a sheet is already safe: maatwebsite returns the raw formula string
 * because $calculateFormulas defaults to false, so nothing is evaluated on the
 * way in. Writing is where the danger actually lives, so every export runs its
 * values through here. A leading apostrophe forces the spreadsheet to treat the
 * value as literal text, and it is not shown to the reader.
 */
trait SanitizesCells
{
    private const DANGEROUS = ['=', '+', '-', '@', "\t", "\r"];

    protected function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::DANGEROUS, true) ? "'" . $value : $value;
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return array<int|string, mixed>
     */
    protected function sanitizeRow(array $row): array
    {
        return array_map(fn ($v) => $this->sanitizeCell($v), $row);
    }
}
