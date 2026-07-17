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
            'attachments' => ['nullable', 'array', 'max:' . AttachmentService::MAX_PER_TICKET],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:5120'],
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
