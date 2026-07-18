<?php

namespace App\Http\Requests\Tickets;

use App\Models\TicketStatusDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTicketStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeStatus', $this->route('ticket'));
    }

    public function rules(): array
    {
        $ticket = $this->route('ticket');
        $allowed = TicketStatusDefinition::transitionMap()[$ticket->status->value] ?? [];

        return [
            'to_status' => ['required', 'string', Rule::in($allowed)],
            'recipient_type' => ['nullable', 'string', Rule::in(['team', 'company'])],
            'recipient_user_id' => [
                'required_if:recipient_type,team',
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],
            'recipient_contact_id' => [
                'required_if:recipient_type,company',
                'nullable', 'integer',
                Rule::exists('company_contacts', 'id')
                    ->where('company_id', $ticket->company_id)
                    ->where('is_active', true),
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'to_status' => 'الحالة الجديدة',
            'recipient_user_id' => 'الشخص من التيم',
            'recipient_contact_id' => 'الشخص من الشركة',
            'note' => 'ملاحظة',
        ];
    }

    public function messages(): array
    {
        return [
            'to_status.in' => 'مينفعش تنقل التذكرة للحالة دي.',
            'recipient_user_id.required_if' => 'لازم تختار الشخص من التيم.',
            'recipient_contact_id.required_if' => 'لازم تختار الشخص من الشركة.',
        ];
    }
}
