<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('time.log')
            && $this->user()->can('view', $this->route('ticket'));
    }

    public function rules(): array
    {
        $ticket = $this->route('ticket');

        return [
            // F09: quarter of an hour to a full day. The DB CHECK repeats this.
            'hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            // You can't have spent time you haven't reached yet.
            'spent_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            // The subtask has to belong to the ticket in the url.
            'subtask_id' => [
                'nullable', 'integer',
                Rule::exists('ticket_subtasks', 'id')->where('ticket_id', $ticket->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'hours' => 'الساعات',
            'spent_on' => 'التاريخ',
            'note' => 'الملاحظة',
            'subtask_id' => 'الصب تاسك',
        ];
    }

    public function messages(): array
    {
        return [
            'spent_on.before_or_equal' => 'مينفعش تسجّل وقت في المستقبل.',
            'hours.min' => 'أقل وقت ممكن تسجّله ربع ساعة.',
            'hours.max' => 'مينفعش تسجّل أكتر من 24 ساعة في اليوم الواحد.',
        ];
    }
}
