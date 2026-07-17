<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Models\ImportBatch;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The failed rows only, each with the reason — so the user fixes those and
 * re-uploads, instead of hunting through the original file (F02).
 */
class ImportErrorsExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize
{
    use Exportable, SanitizesCells;

    public function __construct(private readonly ImportBatch $batch)
    {
    }

    public function headings(): array
    {
        return array_merge(['رقم الصف', 'العمود', 'سبب الرفض'], $this->batch->type->columns());
    }

    public function array(): array
    {
        return array_map(function (array $error) {
            $data = $error['data'] ?? [];
            $values = array_map(
                fn (string $column) => $data[$column] ?? null,
                $this->batch->type->columns()
            );

            return $this->sanitizeRow(array_merge(
                [$error['row'], $error['column'], $error['reason']],
                $values
            ));
        }, $this->batch->errors ?? []);
    }

    public function title(): string
    {
        return 'الصفوف الفاشلة';
    }
}
