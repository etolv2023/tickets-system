<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads a sheet into a plain array and stops there.
 *
 * Deliberately not ToModel: F02 requires a preview before a single row is
 * written, so reading and writing have to be separate steps. ImportService owns
 * the validation and the writing; this only turns a file into rows.
 *
 * ★ First sheet only. The downloaded template carries a second sheet of
 * instructions, and reading every sheet into the same property meant the last
 * one won: an upload of the template, filled in exactly as told, came back as
 * the instruction rows and failed every one of them.
 */
class SheetReader implements ToArray, WithHeadingRow, SkipsEmptyRows, WithMultipleSheets
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }

    /** @return array<int, self> */
    public function sheets(): array
    {
        return [0 => $this];
    }

    /** Row 1 holds the column names. */
    public function headingRow(): int
    {
        return 1;
    }
}
