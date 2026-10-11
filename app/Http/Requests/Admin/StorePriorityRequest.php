<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:priorities,key'],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            // Only the semantic palette — no free colour picker (§ 6).
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
            // One hour minimum, one year maximum — anything outside that is a typo.
            'sla_hours' => ['required', 'integer', 'between:1,8760'],
        ];
    }

    public function attributes(): array
    {
        return [
            'key' => __('المفتاح'),
            'name_ar' => __('الاسم'),
            'name_en' => __('الاسم بالإنجليزية'),
            'color' => __('اللون'),
            'sla_hours' => __('مهلة SLA'),
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => __('يجب أن يتكون المفتاح من أحرف إنجليزية صغيرة وأرقام و_ فقط، وألّا يبدأ برقم.'),
            'key.unique' => __('هذا المفتاح مستخدم بالفعل.'),
        ];
    }
}
