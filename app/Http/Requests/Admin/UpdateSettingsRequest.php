<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'app_name' => ['required', 'string', 'max:100'],
            // The extension is only a first filter; SettingService re-checks the
            // real MIME with finfo before touching the file (CLAUDE.md § 5).
            'app_logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'daily_capacity_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'week_start_day' => ['required', 'integer', 'between:0,6'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:0,6', 'distinct'],
            // The SLA clock's window. F14
            'work_day_start' => ['required', 'date_format:H:i'],
            'work_day_end' => ['required', 'date_format:H:i', 'after:work_day_start'],
            'email_notifications_enabled' => ['nullable', 'boolean'],
            // ★ (2026-08-05) Whether the 06:00 late sweep docks the same subtask
            // again every morning it stays overdue, or only once ever.
            'late_penalty_accumulates' => ['nullable', 'boolean'],
            // Who the mail comes from. A setting, not a config constant, so it
            // changes without a deploy.
            'mail_from_address' => ['nullable', 'email', 'max:150'],
            'mail_from_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Unchecked boxes never reach the server; make their absence explicit.
        $this->merge([
            'email_notifications_enabled' => $this->boolean('email_notifications_enabled'),
            'late_penalty_accumulates' => $this->boolean('late_penalty_accumulates'),
            'remove_logo' => $this->boolean('remove_logo'),
        ]);

        // Checkbox values arrive as strings. Without this the setting round-trips
        // as ["0","1"] while the seeder writes [0,1], and the calendar's day
        // comparisons (F13/F14) would silently stop matching.
        if (is_array($this->input('work_days'))) {
            $this->merge([
                'work_days' => array_values(array_map('intval', $this->input('work_days'))),
            ]);
        }
    }

    public function messages(): array
    {
        return [
            'work_days.required' => 'اختار يوم عمل واحد على الأقل.',
            'work_day_end.after' => 'نهاية الدوام لازم تكون بعد بدايته.',
        ];
    }
}
