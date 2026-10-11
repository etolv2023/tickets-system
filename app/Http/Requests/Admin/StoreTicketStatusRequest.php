<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            // Lowercase snake_case only — it's a DB key and a URL-safe identifier,
            // never shown to anyone (name_ar is what renders).
            'key' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:ticket_statuses,key'],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:255'],
            // Only the semantic palette — no free colour picker (§ 6).
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
            'is_open' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'key' => __('المفتاح'),
            'name_ar' => __('الاسم'),
            'color' => __('اللون'),
            'is_open' => __('مفتوحة'),
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('المفتاح لازم يكون حروف إنجليزي صغير وأرقام و_ بس، وميبدأش برقم.'),
            'key.unique' => __('المفتاح ده مستخدم قبل كده.'),
        ];
    }
}
