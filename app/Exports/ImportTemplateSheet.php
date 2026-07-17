<?php

namespace App\Exports;

use App\Enums\ImportType;
use App\Exports\Concerns\SanitizesCells;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ImportTemplateSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    use SanitizesCells;

    public function __construct(private readonly ImportType $type)
    {
    }

    public function headings(): array
    {
        return $this->type->columns();
    }

    public function array(): array
    {
        // One filled row so the expected format is obvious, not described.
        return [$this->sanitizeRow($this->type->sampleRow())];
    }

    public function title(): string
    {
        return 'البيانات';
    }
}
