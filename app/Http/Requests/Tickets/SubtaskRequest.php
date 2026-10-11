<?php

namespace App\Http\Requests\Tickets;

use App\Casts\SubtaskStatusValue;
use App\Models\Role;
use App\Models\SubtaskStatusDefinition;
use App\Models\Ticket;
use App\Models\TicketSubtask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subtask = $this->route('subtask');

        return $subtask instanceof TicketSubtask
            ? $this->user()->can('update', $subtask)
            : $this->user()->can('create', [TicketSubtask::class, $this->route('ticket')]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // ★ (2026-08-02) Optional while the subtask is still open — writing
            // down a step before knowing who takes it is real planning. Required
            // the moment it is marked done: PointEngineService pays per
            // assignee, so a finished subtask with nobody on it is work that can
            // never be paid (the TK-2026-00169 failure). Same gate as
            // SubtaskStatusRequest, for the full form.
            'assignee_id' => [
                Rule::requiredIf(fn () => SubtaskStatusValue::for((string) $this->input('status'))->isDone()),
                'nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true),
            ],
            // A subtask is categorised by its role now (2026-07-24): the dynamic
            // list of assignable roles from the DB, not a hardcoded side.
            // Optional — a general step can stay uncategorised.
            'role_id' => ['nullable', 'integer', Rule::in(Role::assignableList()->pluck('id'))],
            // Dynamic since 2026-07-24: any status the admin defined.
            'status' => ['required', Rule::in(array_keys(SubtaskStatusDefinition::map()))],
            // A subtask carries one date. It is estimated in hours, so a
            // multi-day start..due span contradicted its own estimate; the due
            // date is what the calendar and "due today" both read.
            'due_date' => ['nullable', 'date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            // F18: left blank on creation, SubtaskService fills the flat
            // default. Always editable from then on — this is the subtask's
            // own points, not a read-only echo of the ticket.
            'points' => ['nullable', 'numeric', 'min:0', 'max:999'],
            // F08: a status flagged needs_reason (blocked out of the box) can't
            // be set without a reason.
            'blocked_reason' => [
                Rule::requiredIf(fn () => (SubtaskStatusDefinition::map()[$this->input('status')] ?? null)?->needs_reason ?? false),
                'nullable', 'string', 'max:255',
            ],
        ];
    }

    /**
     * F08: a subtask due after the ticket's SLA is a warning, not an error —
     * the plan may legitimately overrun, but somebody should see it.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $ticket = $this->route('ticket') ?? $this->route('subtask')?->ticket;
                $due = $this->input('due_date');

                if (! $ticket instanceof Ticket || blank($due) || $ticket->sla_due_at === null) {
                    return;
                }

                if (\Carbon\Carbon::parse($due)->endOfDay()->gt($ticket->sla_due_at)) {
                    session()->flash('subtask_warning',
                        __('تنبيه: تاريخ الاستحقاق بعد مهلة SLA للتذكرة، وقد سُجّل على أي حال.'));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('العنوان'),
            'description' => __('الوصف'),
            'assignee_id' => __('المسؤول'),
            'role_id' => __('الدور'),
            'status' => __('الحالة'),
            'start_date' => __('تاريخ البداية'),
            'due_date' => __('تاريخ الاستحقاق'),
            'estimated_hours' => __('التقدير بالساعات'),
            'points' => __('النقاط'),
            'blocked_reason' => __('سبب التوقف'),
        ];
    }

    public function messages(): array
    {
        return [
            'due_date.after_or_equal' => __('لا يمكن أن يكون تاريخ الاستحقاق قبل تاريخ البداية.'),
            'blocked_reason.required' => __('اكتب سبب التوقف.'),
            'assignee_id.required' => __('حدّد صاحب المهمة الفرعية قبل إنهائها — العمل الذي بلا صاحب لا يُحتسب له نقاط.'),
        ];
    }
}
