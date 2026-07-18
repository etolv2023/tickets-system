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
            'confirm' => ['required', Rule::in([BackupService::CONFIRM_PHRASE])],
        ];
    }

    public function attributes(): array
    {
        return ['file' => 'ملف الباك أب', 'confirm' => 'كلمة التأكيد'];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'الملف كبير أوي. الحد 500 ميجا.',
            'confirm.in' => 'لازم تكتب «' . BackupService::CONFIRM_PHRASE . '» بالظبط عشان تأكد.',
        ];
    }
}
