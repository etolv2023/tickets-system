<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('users.manage');
    }

    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            // The key is code-facing, so it stays ASCII and immutable in spirit;
            // the Arabic name is what users read.
            'key' => [
                'required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'key')->ignore($role?->id),
            ],
            'name_ar' => ['required', 'string', 'max:100'],
            'assignable_on_tickets' => ['nullable', 'boolean'],
            // Two role behaviours the distribution rework made into flags
            // (2026-07-24): logs_work → the assignment gets a بدأت/خلصت work
            // log; is_tester → the ticket enters the testing queue.
            'logs_work' => ['nullable', 'boolean'],
            'is_tester' => ['nullable', 'boolean'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'الكود لازم يبدأ بحرف إنجليزي صغير، ويحتوي حروف صغيرة وأرقام و _ بس.',
        ];
    }
}
