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
