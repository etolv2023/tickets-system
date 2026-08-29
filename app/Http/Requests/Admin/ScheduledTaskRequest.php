<?php

namespace App\Http\Requests\Admin;

use App\Support\CronPreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * ★ (2026-08-29) A task's schedule and its on/off switch. Nothing else.
 *
 * There is no `key` and no `command` in these rules, and that is the whole
 * design: the row being edited is bound by the route, its key is already in the
 * database, and the command it means comes from ScheduledTaskRegistry in code.
 * Nothing a person types can name a command to run.
 *
 * The cron expression is built from the dropdowns (or taken raw behind
 * «متقدم») and then checked by CronPreset::isValid(), which asks two things:
 * does it parse, and can it ever fire. The second matters — five syntactically
 * legal fields can describe the 30th of February, which every parser accepts
 * and which then never runs, silently. See that method.
 */
class ScheduledTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('schedule.manage');
    }

    public function rules(): array
    {
        return [
            'frequency' => ['required', Rule::in(array_keys(CronPreset::FREQUENCIES))],
            'minute' => ['nullable', 'integer', 'min:0', 'max:59'],
            'hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'weekday' => ['nullable', 'integer', 'min:0', 'max:6'],
            'monthday' => ['nullable', 'integer', 'min:1', 'max:28'],
            // Only read when frequency is 'custom'; validated below either way.
            'cron' => ['nullable', 'string', 'max:100'],
            'is_enabled' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $cron = CronPreset::toExpression($this->validated());

                if (! CronPreset::isValid($cron)) {
                    $validator->errors()->add(
                        'cron',
                        'الصيغة دي مش cron صحيح. الشكل خمس خانات — دقيقة، ساعة، يوم الشهر، الشهر، يوم الأسبوع.'
                    );
                }
            },
        ];
    }

    /** The expression to store — built once, here, so the controller cannot build it differently. */
    public function cronExpression(): string
    {
        return CronPreset::toExpression($this->validated());
    }

    public function attributes(): array
    {
        return [
            'frequency' => 'التكرار',
            'minute' => 'الدقيقة',
            'hour' => 'الساعة',
            'weekday' => 'يوم الأسبوع',
            'monthday' => 'يوم الشهر',
            'cron' => 'صيغة الـ cron',
        ];
    }
}
