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
            // `extensions`, not `mimes`: mimes guesses an extension from the
            // bytes, and a CSV's bytes are plain text, so every real .csv was
            // refused as "txt" before it reached that check.
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv', 'max:10240'],
        ];
    }

    public function attributes(): array
    {
        return ['file' => __('الملف'), 'type' => __('نوع الاستيراد')];
    }

    public function messages(): array
    {
        return [
            'file.max' => __('الملف كبير جدًا. الحد الأقصى 10 ميغابايت و:rows صف.', ['rows' => ImportService::MAX_ROWS]),
        ];
    }
}
