<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F28 — what the lookup accepts.
 *
 * authorize() is true because VerifyIntegrationSignature already proved the
 * caller holds the shared secret; there is no user to check a permission
 * against. The caps are the real work: the search term goes into a FULLTEXT
 * query and the result count is bounded so one call can never page through the
 * whole table.
 */
class TicketLookupRequest extends FormRequest
{
    public const MAX_LIMIT = 20;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ];
    }

    public function term(): string
    {
        return trim((string) $this->validated('q', ''));
    }

    public function limit(): int
    {
        return (int) ($this->validated('limit') ?? self::MAX_LIMIT);
    }
}
