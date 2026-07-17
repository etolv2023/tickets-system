<?php

namespace App\Http\Requests\Admin;

use App\Models\CompanyContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('companies.manage');
    }

    public function rules(): array
    {
        /** @var CompanyContact|null $contact */
        $contact = $this->route('contact');
        $companyId = $this->route('company')->id;

        return [
            'name' => ['required', 'string', 'max:150'],
            // Unique within the company only: two customers may each have an
            // employee 1042 in their own ERP. F01
            'erp_employee_id' => [
                'required', 'string', 'max:50',
                Rule::unique('company_contacts', 'erp_employee_id')
                    ->where('company_id', $companyId)
                    ->ignore($contact?->id),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function messages(): array
    {
        return [
            'erp_employee_id.unique' => 'رقم الموظف ده مستخدم قبل كده في نفس الشركة.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'اسم جهة الاتصال',
            'erp_employee_id' => 'رقم الموظف في الـ ERP',
            'email' => 'الإيميل',
            'phone' => 'التليفون',
        ];
    }
}
