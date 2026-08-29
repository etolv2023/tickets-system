<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ★ (2026-08-29) F18 — the corrected values for a manual correction.
 *
 * Every field the create form takes, and for the same reason: if a correction
 * went to the wrong person or under the wrong role, that is exactly the kind of
 * mistake this button exists to fix, and forcing "cancel it, then type it all
 * again" would be the same two rows with more chances to fat-finger the second.
 *
 * points is `not_in:0` like the create form — a zero-point correction is a row
 * that says nothing, and the way to make a correction stop counting is «حذف»,
 * which writes a reversal and says so.
 */
class UpdatePointCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('points.corrections.edit');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'points' => ['required', 'numeric', 'min:-999', 'max:999', 'not_in:0'],
            'role_id' => ['required', 'integer', Rule::in(Role::assignableList()->pluck('id'))],
            'reason' => ['required', 'string', 'max:255'],
            'ticket_number' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'المستخدم',
            'points' => 'النقاط',
            'role_id' => 'الدور',
            'reason' => 'السبب',
            'ticket_number' => 'رقم التذكرة',
        ];
    }
}
