<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLinkTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:link_types,key'],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'inverse_label_ar' => ['required', 'string', 'max:100'],
            'inverse_label_en' => ['nullable', 'string', 'max:100'],
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
        ];
    }

    public function attributes(): array
    {
        return [
            'key' => __('المفتاح'),
            'name_ar' => __('الاسم من التذكرة الأصلية'),
            'name_en' => __('الاسم من التذكرة الأصلية بالإنجليزية'),
            'inverse_label_ar' => __('الاسم العكسي'),
            'inverse_label_en' => __('الاسم العكسي بالإنجليزية'),
            'color' => __('اللون'),
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
