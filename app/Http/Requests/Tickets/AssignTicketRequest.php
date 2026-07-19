<?php

namespace App\Http\Requests\Tickets;

use App\Enums\UserSkill;
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
            // skills, not role, decides who may take which side. A "both"
            // developer is valid for either list. F00.3
            'assigned_frontend_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->whereIn('skills', [UserSkill::Frontend->value, UserSkill::Both->value]),
            ],
            'assigned_backend_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->whereIn('skills', [UserSkill::Backend->value, UserSkill::Both->value]),
            ],
            'tester_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
            // Role, not skills — same as the tester. `skills` is a flat enum
            // whose "both" already means frontend+backend, so a third side has
            // no expressible value there.
            'devops_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'assigned_frontend_id' => 'مبرمج الفرونت',
            'assigned_backend_id' => 'مبرمج الباك',
            'tester_id' => 'التيستر',
            'devops_id' => 'الديف أوبس',
        ];
    }

    public function messages(): array
    {
        return [
            'assigned_frontend_id.exists' => 'المستخدم ده مش متاح كمبرمج فرونت.',
            'assigned_backend_id.exists' => 'المستخدم ده مش متاح كمبرمج باك.',
        ];
    }
}
