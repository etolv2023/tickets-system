<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubtaskStatusRequest extends FormRequest
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
        // key is immutable — it's what ticket_subtasks.status stores.
        return [
            'name_ar' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
            'needs_reason' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_ar' => 'الاسم',
            'color' => 'اللون',
            'needs_reason' => 'يحتاج سبب',
        ];
    }
}
