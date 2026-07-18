<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            // The key is never editable — it's what tickets.priority actually
            // stores, and SlaService reads sla_hours off this exact row.
            'name_ar' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
            'sla_hours' => ['required', 'integer', 'between:1,8760'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_ar' => 'الاسم',
            'color' => 'اللون',
            'sla_hours' => 'مهلة الـ SLA',
        ];
    }
}
