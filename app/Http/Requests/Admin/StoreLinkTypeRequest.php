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
            'inverse_label_ar' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
        ];
    }

    public function attributes(): array
    {
        return [
            'key' => 'المفتاح',
            'name_ar' => 'الاسم من التذكرة',
            'inverse_label_ar' => 'الاسم العكسي',
            'color' => 'اللون',
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'المفتاح لازم يكون حروف إنجليزي صغير وأرقام و_ بس، وميبدأش برقم.',
            'key.unique' => 'المفتاح ده مستخدم قبل كده.',
        ];
    }
}
