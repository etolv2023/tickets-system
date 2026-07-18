<?php

namespace App\Http\Requests\Tickets;

use App\Enums\TicketScope;
use App\Enums\TicketType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ticket'));
    }

    public function rules(): array
    {
        // Company, contact and reported_at are deliberately absent: they are the
        // snapshot of who reported what and when, and editing them would rewrite
        // history (F03).
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:200000'],
            'type' => ['required', Rule::enum(TicketType::class)],
            'scope' => ['required', Rule::enum(TicketScope::class)],
            'priority' => ['required', Rule::exists('priorities', 'key')],
            'module' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'description' => 'الوصف',
            'type' => 'النوع',
            'scope' => 'النطاق',
            'priority' => 'الأولوية',
            'module' => 'الموديول',
        ];
    }
}
