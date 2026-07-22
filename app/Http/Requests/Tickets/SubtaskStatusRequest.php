<?php

namespace App\Http\Requests\Tickets;

use App\Models\SubtaskStatusDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Status on its own. SubtaskRequest can't serve this: it marks title, side and
 * status all required, so a one-field PATCH would fail validation on the two
 * fields it never sends.
 */
class SubtaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subtask = $this->route('subtask');

        return $this->user()->can('update', [$subtask, $subtask->ticket]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // A reason-requiring status (blocked out of the box) is deliberately
            // absent: a one-click control has nowhere to ask for the reason, so
            // those stay in the full edit form. quickChangeKeys() is every
            // status that doesn't need a reason.
            'status' => ['required', Rule::in(SubtaskStatusDefinition::quickChangeKeys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => 'الحالة'];
    }
}
