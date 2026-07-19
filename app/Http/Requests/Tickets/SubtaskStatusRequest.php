<?php

namespace App\Http\Requests\Tickets;

use App\Enums\SubtaskStatus;
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
            // Blocked is deliberately absent: it needs a reason (F08), and a
            // one-click control has nowhere to ask for one. Blocking stays in
            // the full edit form.
            'status' => ['required', Rule::in([
                SubtaskStatus::Todo->value,
                SubtaskStatus::InProgress->value,
                SubtaskStatus::Done->value,
            ])],
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
