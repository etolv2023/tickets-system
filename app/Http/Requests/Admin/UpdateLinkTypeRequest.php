<?php

namespace App\Http\Requests\Admin;

use App\Models\Label;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLinkTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        // key is immutable — it's what ticket_links.type stores.
        return [
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
            'name_ar' => __('الاسم من التذكرة'),
            'name_en' => __('الاسم من التذكرة بالإنجليزية'),
            'inverse_label_ar' => __('الاسم العكسي'),
            'inverse_label_en' => __('الاسم العكسي بالإنجليزية'),
            'color' => __('اللون'),
        ];
    }
}
