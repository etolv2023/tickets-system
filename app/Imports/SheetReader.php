<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Reads a sheet into a plain array and stops there.
 *
 * Deliberately not ToModel: F02 requires a preview before a single row is
 * written, so reading and writing have to be separate steps. ImportService owns
 * the validation and the writing; this only turns a file into rows.
 */
class SheetReader implements ToArray, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }

    /** Row 1 holds the column names. */
    public function headingRow(): int
    {
        return 1;
    }
}
