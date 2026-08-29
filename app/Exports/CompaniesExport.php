<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Models\Company;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/** F01 — the customer list. */
class CompaniesExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable, SanitizesCells;

    /** @param array<string, mixed> $filters */
    public function __construct(private readonly array $filters = [])
    {
    }

    public function query()
    {
        // The same filter chain the screen runs — see CompanyController::index.
        return Company::query()
            ->select(['id', 'name', 'code', 'is_active', 'notes'])
            ->withCount('contacts')
            ->search($this->filters['q'] ?? null)
            ->when(($this->filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($this->filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['الشركة', 'الكود', 'جهات الاتصال', 'الحالة', 'ملاحظات'];
    }

    /** @param Company $company */
    public function map($company): array
    {
        return $this->sanitizeRow([
            $company->name,
            $company->code,
            $company->contacts_count,
            $company->is_active ? 'مفعّلة' : 'موقوفة',
            $company->notes,
        ]);
    }

    public function title(): string
    {
        return 'الشركات';
    }
}
