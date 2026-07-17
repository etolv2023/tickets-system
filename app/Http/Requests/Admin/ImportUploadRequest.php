<?php

namespace App\Http\Requests\Admin;

use App\Enums\ImportType;
use App\Services\ImportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = ImportType::tryFrom((string) $this->input('type'));

        // Companies and contacts need companies.manage; users needs users.manage. F02
        return $type !== null && $this->user()->hasPermission($type->permission());
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ImportType::class)],
            // The extension is the first filter only; ImportService re-checks the
            // real bytes with finfo before reading anything (CLAUDE.md § 5).
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'الملف', 'type' => 'نوع الاستيراد'];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'الملف كبير أوي. الحد 10 ميجا و ' . ImportService::MAX_ROWS . ' صف.',
        ];
    }
}
