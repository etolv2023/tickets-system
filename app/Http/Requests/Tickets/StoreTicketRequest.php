<?php

namespace App\Http\Requests\Tickets;

use App\Enums\Priority;
use App\Enums\TicketScope;
use App\Enums\TicketType;
use App\Models\Company;
use App\Services\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Ticket::class);
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
            'contact_id' => ['nullable', 'integer', 'exists:company_contacts,id'],
            // Only needed when the reporter isn't a saved contact.
            'reporter_name' => ['required_without:contact_id', 'nullable', 'string', 'max:150'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:200000'],
            'type' => ['required', Rule::enum(TicketType::class)],
            'scope' => ['required', Rule::enum(TicketScope::class)],
            'priority' => ['required', Rule::enum(Priority::class)],
            'module' => ['nullable', 'string', 'max:100'],
            'attachments' => ['nullable', 'array', 'max:' . AttachmentService::MAX_PER_TICKET],
            // The real type is re-checked with finfo in AttachmentService;
            // this only keeps the obvious junk out early.
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $contactId = $this->input('contact_id');

                if (blank($contactId)) {
                    return;
                }

                // A contact from another company would silently mislabel the
                // reporter, so the pair has to be checked together.
                $belongs = Company::query()
                    ->where('id', $this->input('company_id'))
                    ->whereHas('contacts', fn ($q) => $q->where('id', $contactId))
                    ->exists();

                if (! $belongs) {
                    $validator->errors()->add('contact_id', 'جهة الاتصال دي مش تابعة للشركة المختارة.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => 'الشركة',
            'contact_id' => 'جهة الاتصال',
            'reporter_name' => 'اسم المُبلغ',
            'title' => 'العنوان',
            'description' => 'الوصف',
            'type' => 'النوع',
            'scope' => 'النطاق',
            'priority' => 'الأولوية',
            'module' => 'الموديول',
            'attachments' => 'المرفقات',
            'attachments.*' => 'المرفق',
        ];
    }
}
