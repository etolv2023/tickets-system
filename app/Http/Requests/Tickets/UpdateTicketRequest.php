<?php

namespace App\Http\Requests\Tickets;

use App\Rules\ResolvableHost;
use App\Services\AttachmentService;
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
        /** @var \App\Models\Ticket $ticket */
        $ticket = $this->route('ticket');

        // ★ (2026-08-04) Read off the ticket, not off the request: is_internal
        // is not an editable field here, so trusting input would let a posted
        // flag turn a client ticket's required fields optional.
        $internal = $ticket->isInternal();

        // The DNS check runs only on a link the user actually just changed.
        //
        // Editing a ticket is mostly changing its priority or fixing a typo in
        // the title, and re-validating an untouched field would let a customer
        // letting their domain lapse — or moving the system behind a VPN —
        // silently freeze every old ticket that names it. The value was checked
        // when it was entered; re-checking it is only meaningful for a new one.
        $urlRules = ['url:http,https', 'max:2048'];

        if ((string) $this->input('page_url') !== (string) $ticket->page_url) {
            $urlRules[] = new ResolvableHost();
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:200000'],
            'type' => ['required', Rule::exists('ticket_types', 'key')],
            'priority' => ['required', Rule::exists('priorities', 'key')],
            'module' => ['nullable', 'string', 'max:100'],

            // Editable after the fact, unlike the company and the reporter:
            // a client hands over a different account, or the page moves.
            'client_user_code' => [
                Rule::requiredIf(! $internal), 'nullable', 'digits_between:1,50',
            ],
            'page_url' => [Rule::requiredIf(! $internal), 'nullable', ...$urlRules],

            // ★ (2026-08-04) The edit form carries an uploader now, so it has
            // to validate what it accepts — this request had no file rules at
            // all, which is what a form with no uploader needs and nothing more.
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,webm,mov',
                'max:' . intdiv(AttachmentService::MAX_VIDEO_BYTES, 1024),
            ],

            // One per file, positionally. Only a paste into the editor produces
            // a real one; a file picked from disk posts an empty string.
            'attachment_tokens' => ['nullable', 'array'],
            'attachment_tokens.*' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{0,64}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'العنوان',
            'description' => 'الوصف',
            'type' => 'النوع',
            'priority' => 'الأولوية',
            'module' => 'الموديول',
            'client_user_code' => 'يوزر الدخول',
            'page_url' => 'لينك الصفحة',
            'attachments' => 'المرفقات',
            'attachments.*' => 'المرفق',
        ];
    }

    public function messages(): array
    {
        return [
            'client_user_code.digits_between' => 'يوزر الدخول أرقام بس.',
            'page_url.url' => 'اللينك لازم يبدأ بـ http:// أو https://.',
        ];
    }
}
