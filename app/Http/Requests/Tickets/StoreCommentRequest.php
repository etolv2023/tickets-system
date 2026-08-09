<?php

namespace App\Http\Requests\Tickets;

use App\Services\AttachmentService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('comment', $this->route('ticket'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:200000'],
            'is_internal' => ['boolean'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,gif,webp,pdf,mp4,webm,mov',
                'max:' . intdiv(AttachmentService::MAX_VIDEO_BYTES, 1024),
            ],

            // One per file, positionally — which upload the body's inline <img>
            // points at. See StoreTicketRequest for the full story.
            'attachment_tokens' => ['nullable', 'array'],
            'attachment_tokens.*' => ['nullable', 'string', 'regex:/^[A-Za-z0-9-]{0,64}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Only someone with comments.internal may mark a note internal —
        // otherwise the checkbox could be forged in the request.
        $this->merge([
            'is_internal' => $this->boolean('is_internal')
                && $this->user()->can('commentInternally', $this->route('ticket')),
        ]);
    }

    public function attributes(): array
    {
        return ['body' => 'التعليق', 'attachments' => 'المرفقات', 'attachments.*' => 'المرفق'];
    }

    public function messages(): array
    {
        return ['body.required' => 'اكتب تعليق الأول.'];
    }
}
