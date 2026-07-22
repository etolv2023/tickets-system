<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubtaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['needs_reason' => $this->boolean('needs_reason')]);
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:subtask_statuses,key'],
            'name_ar' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
            'needs_reason' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'key' => 'المفتاح',
            'name_ar' => 'الاسم',
            'color' => 'اللون',
            'needs_reason' => 'يحتاج سبب',
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
