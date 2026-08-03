<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use App\Models\TicketTypeDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['needs_approval' => $this->boolean('needs_approval')]);
    }

    public function rules(): array
    {
        // The key is never editable — it's what tickets.type actually stores.
        return [
            'name_ar' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
            'icon' => ['required', Rule::in(array_keys(TicketTypeDefinition::ICONS))],
            'needs_approval' => ['boolean'],
            // Changing this only moves what the NEXT subtask opens at — every
            // subtask already created keeps the number it carries (F18).
            'default_points' => ['required', 'numeric', 'min:0', 'max:999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name_ar' => 'الاسم',
            'color' => 'اللون',
            'icon' => 'الأيقونة',
            'needs_approval' => 'يحتاج موافقة',
            'default_points' => 'النقاط الافتراضية',
        ];
    }
}
