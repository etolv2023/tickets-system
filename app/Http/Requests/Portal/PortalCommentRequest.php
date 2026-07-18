<?php

namespace App\Http\Requests\Portal;

use App\Models\Ticket;
use App\Support\PortalAccess;
use Illuminate\Foundation\Http\FormRequest;

class PortalCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = Ticket::where('ticket_number', $this->route('ticketNumber'))->first();

        return $ticket !== null
            && PortalAccess::allows($this, $ticket->id);
    }

    public function rules(): array
    {
        $max = (int) config('uploads.portal.max_bytes', 10 * 1024 * 1024);

        return [
            // Editor HTML, purified server-side by TicketService before it is
            // stored — which is the control CLAUDE.md § 5 actually asks for.
            //
            // Two ceilings, because one is not enough once markup is allowed:
            // 5000 characters of TEXT is the limit that means something to a
            // person, and the raw cap stops a payload that is all tags and no
            // words from getting as far as the purifier.
            'body' => ['required', 'string', 'max:40000', function (string $attribute, mixed $value, callable $fail) {
                if (mb_strlen(trim(strip_tags((string) $value))) > 5000) {
                    $fail('الرسالة أطول من المسموح (5000 حرف).');
                }
            }],

            // These rules are the outer fence only. The real gate is
            // AttachmentService, which sniffs the bytes, scans them and
            // re-encodes images — 'file' here just keeps obvious junk out of
            // the pipeline early.
            'files' => ['sometimes', 'array', 'max:' . (int) config('uploads.portal.max_per_comment', 3)],
            'files.*' => ['file', 'max:' . intdiv($max, 1024)],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => 'التعليق',
            'files' => 'المرفقات',
            'files.*' => 'المرفق',
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'اكتب رسالتك الأول.',
            'files.max' => 'مسموح بـ :max ملفات في الرسالة الواحدة.',
            'files.*.max' => 'الملف أكبر من المسموح.',
        ];
    }
}
