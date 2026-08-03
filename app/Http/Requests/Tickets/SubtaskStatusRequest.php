<?php

namespace App\Http\Requests\Tickets;

use App\Casts\SubtaskStatusValue;
use App\Models\SubtaskStatusDefinition;
use Illuminate\Contracts\Validation\Validator;
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
     * ★ (2026-08-02) The one gate that makes an unpaid subtask impossible.
     *
     * An unowned subtask is a legitimate plan — somebody wrote down a step
     * before knowing who takes it. An unowned subtask that is DONE is not: the
     * work happened, and PointEngineService filters on assignee_id, so it can
     * never pay anyone. That is exactly how TK-2026-00169 finished seven
     * subtasks and paid nobody.
     *
     * So the check sits on the transition into done rather than on creation —
     * it costs the planner nothing and closes the money hole completely.
     * SubtaskRequest carries the same rule for the full edit form.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Rules already rejected an unknown status; don't pile on.
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $subtask = $this->route('subtask');

                if (! SubtaskStatusValue::for($this->input('status'))->isDone()) {
                    return;
                }

                if ($subtask->assignee_id !== null) {
                    return;
                }

                $validator->errors()->add(
                    'status',
                    'حدّد صاحب الصب تاسك الأول — الشغل اللي مالوش صاحب مش بياخد نقط.'
                );
            },
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
