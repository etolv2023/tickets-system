<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            // The key is never editable — it's what tickets.status actually
            // stores, and every place that names a status by key would go
            // stale silently if it could change underneath them.
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description_ar' => ['nullable', 'string', 'max:255'],
            'description_en' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
            'is_open' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_ar' => __('الاسم'),
            'color' => __('اللون'),
            'is_open' => __('مفتوحة'),
        ];
    }
}
