<?php

namespace App\Http\Requests\Admin;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('companies.manage');
    }

    public function rules(): array
    {
        /** @var Company|null $company */
        $company = $this->route('company');

        return [
            'name' => ['required', 'string', 'max:150'],
            // Same charset the import enforces: the contacts sheet joins on this.
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('companies', 'code')->ignore($company?->id),
            ],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'الكود لازم يكون حروف إنجليزية وأرقام و - و _ بس.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم الشركة', 'code' => 'الكود', 'notes' => 'ملاحظات'];
    }
}
