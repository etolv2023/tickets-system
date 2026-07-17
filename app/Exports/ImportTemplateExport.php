<?php

namespace App\Exports;

use App\Enums\ImportType;
use App\Exports\Concerns\SanitizesCells;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The template a user downloads before filling in their data (F02): correct
 * columns, one worked example, and a sheet explaining every column.
 */
class ImportTemplateExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private readonly ImportType $type)
    {
    }

    public function sheets(): array
    {
        return [
            new ImportTemplateSheet($this->type),
            new ImportInstructionsSheet($this->type),
        ];
    }
}
