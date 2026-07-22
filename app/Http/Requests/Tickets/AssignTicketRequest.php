<?php

namespace App\Http\Requests\Tickets;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            // Distribution is fully role-based (2026-07-24): one entry per
            // assignable role, keyed by role id. Only a role the admin opted in
            // may be assigned (checked in withValidator), and only an active user.
            'role_assignments' => ['nullable', 'array'],
            'role_assignments.*' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
        ];
    }

    /**
     * Every key of role_assignments must itself be an id of a role opted into
     * ticket assignment — validated here rather than as a rule key, since
     * Laravel validates array values, not the keys used to look them up.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $roleIds = Role::query()->assignableOnTickets()->pluck('id')->all();

            foreach (array_keys($this->input('role_assignments', [])) as $roleId) {
                if (! in_array((int) $roleId, $roleIds, true)) {
                    $validator->errors()->add('role_assignments', 'رول مش متاح للتوزيع.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'role_assignments' => 'التوزيع',
        ];
    }

    public function messages(): array
    {
        return [
            'role_assignments.*.exists' => 'المستخدم ده مش متاح للتوزيع.',
        ];
    }
}
