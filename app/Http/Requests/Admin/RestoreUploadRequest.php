<?php

namespace App\Http\Requests\Admin;

use App\Services\BackupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestoreUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('system.backup');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:zip', 'max:512000'],
            // Typed by hand, not a checkbox — a restore replaces every row and
            // every file the system currently holds, and a checkbox is too easy
            // to tick on the way past.
            'confirm' => ['required', Rule::in([BackupService::CONFIRM_PHRASE, BackupService::CONFIRM_PHRASE_EN])],
        ];
    }

    public function attributes(): array
    {
        return ['file' => __('ملف النسخة الاحتياطية'), 'confirm' => __('كلمة التأكيد')];
    }

    public function messages(): array
    {
        return [
            'file.max' => __('الملف كبير جدًا. الحد الأقصى 500 ميغابايت.'),
            'confirm.in' => __('اكتب «:phrase» كما هي تمامًا للتأكيد.', ['phrase' => BackupService::confirmPhrase()]),
        ];
    }
}
