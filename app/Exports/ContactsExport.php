<?php

namespace App\Exports;

use App\Exports\Concerns\SanitizesCells;
use App\Models\CompanyContact;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F01 — contacts across every company.
 *
 * A phone number starting with + is exactly the shape the formula guard exists
 * for: Excel reads +20100… as an expression and shows #NAME?. SanitizesCells
 * forces it back to text.
 */
class ContactsExport implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStrictNullComparison
{
    use Exportable, SanitizesCells;

    /** @param array<string, mixed> $filters */
    public function __construct(private readonly array $filters = [])
    {
    }

    public function query()
    {
        // The same filter chain the screen runs — see ContactController::index.
        return CompanyContact::query()
            ->select(['id', 'company_id', 'name', 'erp_employee_id', 'email', 'phone', 'is_active'])
            ->with('company:id,name,code')
            ->search($this->filters['q'] ?? null)
            ->when($this->filters['company'] ?? null, fn ($q, $v) => $q->where('company_id', $v))
            ->when(($this->filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($this->filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['الاسم', 'الشركة', 'كود الشركة', 'رقم الموظف', 'الإيميل', 'التليفون', 'الحالة'];
    }

    /** @param CompanyContact $contact */
    public function map($contact): array
    {
        return $this->sanitizeRow([
            $contact->name,
            $contact->company?->name,
            $contact->company?->code,
            $contact->erp_employee_id,
            $contact->email,
            $contact->phone,
            $contact->is_active ? 'مفعّلة' : 'موقوفة',
        ]);
    }

    public function title(): string
    {
        return 'جهات الاتصال';
    }
}
