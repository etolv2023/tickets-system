<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('users.manage');
    }

    public function rules(): array
    {
        /** @var User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'daily_capacity_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'is_active' => ['boolean'],
            // Only offered when creating; an edit never touches the password.
            // Resetting is its own explicit action. F22.3
            'password' => [
                $user ? 'nullable' : 'required',
                'string', 'confirmed', Password::min(10),
            ],
            // ★ (2026-08-02) The effective permission set the admin ticked, not
            // the exceptions themselves — UserController diffs it against the
            // role to work out which rows to store. Edit only; the create form
            // renders no picker, and an absent key must not be read as "revoke
            // everything", which is why update() only acts when it is present.
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }
}
