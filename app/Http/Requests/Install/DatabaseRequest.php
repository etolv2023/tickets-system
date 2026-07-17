<?php

namespace App\Http\Requests\Install;

use Illuminate\Foundation\Http\FormRequest;

class DatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The EnsureInstalled middleware is the gate here: once installed.lock
        // exists, this route 404s and can never be reached again.
        return true;
    }

    public function rules(): array
    {
        return [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'between:1,65535'],
            // Whitelisted here because the CREATE DATABASE statement cannot bind
            // this as a parameter. InstallService re-checks the same pattern.
            'db_database' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_]+$/'],
            'db_username' => ['required', 'string', 'max:64'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'db_database.regex' => 'اسم قاعدة البيانات لازم يكون حروف إنجليزية وأرقام و _ بس.',
        ];
    }
}
