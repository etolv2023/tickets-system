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
            // ★ (2026-08-19) F26.1 — same rule as ProfileRequest, because it is
            // the same field: an admin filling it in for somebody must not be
            // able to store a value the person themselves would be refused.
            'discord_user_id' => ['nullable', 'string', 'regex:/^\d{17,20}$/'],
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

            // F07 waivers — who this person may keep waiting. waivers_present is
            // the hidden marker that says the card was on the form at all, so an
            // empty selection reads as "none" instead of "leave them alone".
            'waivers_present' => ['sometimes'],
            'waiver_all' => ['sometimes', 'boolean'],
            'waivers' => ['sometimes', 'array'],
            'waivers.*' => ['integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);

        // A pasted Discord id often arrives wrapped as <@123…>. Same
        // normalisation ProfileRequest does — see the reasoning there.
        $id = $this->input('discord_user_id');

        if (is_string($id)) {
            $id = preg_replace('/^<@!?(\d+)>$/', '$1', trim($id));
            $this->merge(['discord_user_id' => $id === '' ? null : $id]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'discord_user_id.regex' => 'الـ Discord ID لازم يكون رقم (17 لـ 20 رقم)، مش اسم المستخدم.',
        ];
    }
}
