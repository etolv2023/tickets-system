<?php

namespace App\Http\Requests\Tickets;

use App\Models\Company;
use App\Models\Role;
use App\Rules\ResolvableHost;
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
            'type' => ['required', Rule::exists('ticket_types', 'key')],
            'priority' => ['required', Rule::exists('priorities', 'key')],
            'module' => ['nullable', 'string', 'max:100'],

            // ★ (2026-08-04) How whoever picks this up reproduces it. Required
            // on a client ticket and optional on an internal one — internal
            // work is often a migration or a script with no customer-facing
            // page and no client account to sign in with.
            'client_user_code' => [
                Rule::requiredIf(! $internal), 'nullable', 'digits_between:1,50',
            ],
            // ResolvableHost is active_url that knows about private networks:
            // it checks DNS, but skips the check for an IP literal, a
            // single-label host, or a private suffix like .local — a customer
            // running their ERP on 192.168.x has no public record and never
            // will. Deliberately NOT an HTTP request either: these pages sit
            // behind the customer's login and answer 401. The scheme is pinned
            // so a javascript: URL can never reach the href in the view.
            'page_url' => [
                Rule::requiredIf(! $internal), 'nullable',
                'url:http,https', new ResolvableHost(), 'max:2048',
            ],

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

            // ★ (2026-08-04) One token per file, positionally, naming which
            // upload the description's inline <img> points at. store() reads it
            // with input() rather than validated(), so it was reaching the
            // service as unvalidated array input — the shape was only checked
            // deep inside AttachmentService. Checked here as well, where the
            // rest of the request's contract lives. Empty for a file picked
            // from disk: only a paste into the editor mints a token.
            'attachment_tokens' => ['nullable', 'array'],
            'attachment_tokens.*' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{0,64}$/'],

            // F06.3: the role-based distribution block, offered at creation.
            // Ignored server-side for a feature/module ticket (needsApproval)
            // until it's approved — see TicketController. One entry per role.
            'role_assignments' => ['nullable', 'array'],
            'role_assignments.*' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)],

            // F08 — the optional inline plan. store() consumes validated(), so
            // without these rules the rows would be silently dropped.
            'subtasks' => ['nullable', 'array', 'max:20'],
            'subtasks.*.title' => ['required', 'string', 'max:255'],
            // ★ (2026-08-02) The row's role is now who OWNS it, not a loose
            // label: store() turns it into the subtask's assignee, and an
            // unowned subtask can never earn its points (F18). So it is
            // required, and it must be a role this ticket is actually being
            // distributed to — offering a role nobody holds would put the
            // subtask right back in the unassigned state this replaced.
            'subtasks.*.role_id' => [
                'required', 'integer', Rule::in($this->distributedRoleIds()),
            ],
            'subtasks.*.due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * The role ids the create form is assigning someone to, right now. Empty
     * when the user can't assign at all — and then the subtask repeater is
     * hidden, so there is nothing to validate against.
     *
     * @return array<int, int>
     */
    private function distributedRoleIds(): array
    {
        if (! $this->user()->hasPermission('tickets.assign')) {
            return [];
        }

        $assignable = Role::assignableList()->pluck('id')->all();

        return array_values(array_filter(
            array_keys(array_filter($this->input('role_assignments', []), fn ($v) => filled($v))),
            fn ($roleId) => in_array((int) $roleId, $assignable, false)
        ));
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
            'priority' => 'الأولوية',
            'module' => 'الموديول',
            'client_user_code' => 'يوزر الدخول',
            'page_url' => 'لينك الصفحة',
            'attachments' => 'المرفقات',
            'attachments.*' => 'المرفق',
            'subtasks' => 'الصب تاسكس',
            'subtasks.*.title' => 'عنوان الصب تاسك',
            'subtasks.*.role_id' => 'صاحب الصب تاسك',
            'subtasks.*.due_date' => 'تاريخ استحقاق الصب تاسك',
        ];
    }

    public function messages(): array
    {
        return [
            'subtasks.*.role_id.required' => 'لازم تحدد الصب تاسك دي بتاعة مين من اللي وزّعت عليهم.',
            'subtasks.*.role_id.in' => 'الصب تاسك لازم تبقى لواحد من اللي وزّعت عليهم التذكرة.',
            'client_user_code.digits_between' => 'يوزر الدخول أرقام بس.',
            'page_url.url' => 'اللينك لازم يبدأ بـ http:// أو https://.',
        ];
    }
}
