<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class PortalVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ticket_number' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return ['ticket_number' => 'رقم التذكرة', 'password' => 'الباسورد'];
    }
}
