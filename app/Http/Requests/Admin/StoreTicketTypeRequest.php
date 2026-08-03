<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use App\Models\TicketTypeDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketTypeRequest extends FormRequest
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
        return [
            'key' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:ticket_types,key'],
            'name_ar' => ['required', 'string', 'max:100'],
            // Only the semantic palette — no free colour picker (§ 6).
            'color' => ['required', Rule::in(array_keys(Label::COLORS))],
            // Only an icon the <x-icon> component actually draws.
            'icon' => ['required', Rule::in(array_keys(TicketTypeDefinition::ICONS))],
            'needs_approval' => ['boolean'],
            // The points a subtask on this type opens at. 0 is allowed and
            // meaningful: a type whose work is tracked but never paid.
            'default_points' => ['required', 'numeric', 'min:0', 'max:999'],
        ];
    }

    public function attributes(): array
    {
        return [
            'key' => 'المفتاح',
            'name_ar' => 'الاسم',
            'color' => 'اللون',
            'icon' => 'الأيقونة',
            'needs_approval' => 'يحتاج موافقة',
            'default_points' => 'النقاط الافتراضية',
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
