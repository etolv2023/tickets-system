<?php

namespace App\Http\Requests\Tickets;

use App\Enums\SubtaskSide;
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

    /** An internal ticket is raised by the team, not owed to a customer. F25 */
    public function isInternal(): bool
    {
        return $this->boolean('is_internal');
    }

    /**
     * Normalises the two shapes to one before the rules run, so a leftover
     * value from the side the user switched away from cannot be saved.
     */
    protected function prepareForValidation(): void
    {
        $this->merge($this->isInternal()
            ? ['company_id' => null, 'contact_id' => null, 'reporter_name' => null]
            : ['requested_by' => null]);

        // An untouched repeater row is not an error, it is a row the user did
        // not fill. Dropping the blanks here is what makes "empty repeater ==
        // today's behaviour" literally true rather than approximately true —
        // otherwise a row added and then ignored would fail required on title.
        if (is_array($rows = $this->input('subtasks'))) {
            $this->merge(['subtasks' => array_values(array_filter(
                $rows,
                fn ($row) => filled($row['title'] ?? null)
            ))]);
        }
    }

    public function rules(): array
    {
        $internal = $this->isInternal();

        return [
            'is_internal' => ['nullable', 'boolean'],

            // Exactly one origin. required_if rather than a bare required, so
            // an internal ticket is not asked for a customer it does not have.
            'company_id' => [
                Rule::requiredIf(! $internal), 'nullable', 'integer',
                Rule::exists('companies', 'id')->where('is_active', true),
            ],
            'requested_by' => [
                Rule::requiredIf($internal), 'nullable', 'integer',
                Rule::exists('users', 'id')->where('is_active', true),
            ],

            'contact_id' => ['nullable', 'integer', 'exists:company_contacts,id'],
            // Only needed when the reporter isn't a saved contact.
            'reporter_name' => [
                Rule::requiredIf(! $internal && blank($this->input('contact_id'))),
                'nullable', 'string', 'max:150',
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:200000'],
            'type' => ['required', Rule::enum(TicketType::class)],
            'scope' => ['required', Rule::enum(TicketScope::class)],
            'priority' => ['required', Rule::exists('priorities', 'key')],
            'module' => ['nullable', 'string', 'max:100'],
            'attachments' => ['nullable', 'array'],
            // The real type is re-checked with finfo in AttachmentService;
            // this only keeps the obvious junk out early. The per-type size
            // cap (5MB image/PDF, 200MB video) is enforced there too — this
            // is just the outer bound so an oversized file fails fast.
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,webm,mov',
                'max:' . intdiv(AttachmentService::MAX_VIDEO_BYTES, 1024),
            ],

            // F06.3: the same distribution block as the ticket page, offered
            // at creation. Ignored server-side for a feature/module ticket
            // (needsApproval) until it's approved — see TicketController.
            'assigned_frontend_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'assigned_backend_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'tester_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'devops_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],

            // F08 — the optional inline plan. store() consumes validated(), so
            // without these rules the rows would be silently dropped.
            'subtasks' => ['nullable', 'array', 'max:20'],
            'subtasks.*.title' => ['required', 'string', 'max:255'],
            'subtasks.*.side' => ['nullable', Rule::enum(SubtaskSide::class)],
            'subtasks.*.due_date' => ['nullable', 'date'],
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
            'requested_by' => 'طالب الشغل',
            'is_internal' => 'نوع التذكرة',
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
            'assigned_frontend_id' => 'مبرمج فرونت',
            'assigned_backend_id' => 'مبرمج باك',
            'tester_id' => 'تيستر',
            'devops_id' => 'ديف أوبس',
            'subtasks' => 'الصب تاسكس',
            'subtasks.*.title' => 'عنوان الصب تاسك',
            'subtasks.*.side' => 'جهة الصب تاسك',
            'subtasks.*.due_date' => 'تاريخ استحقاق الصب تاسك',
        ];
    }
}
