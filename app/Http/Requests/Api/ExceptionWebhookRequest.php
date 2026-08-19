<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * F26 — what a reporting server is allowed to send.
 *
 * authorize() returns true because authorisation already happened, and happened
 * properly: VerifyWebhookSignature runs before this and proves the caller holds
 * the shared secret. There is no user here to check a permission against — the
 * caller is a queue worker on another host.
 *
 * The size caps are the real work. Everything here becomes a ticket a person
 * has to read, and two of these fields are unbounded at the source: a stack
 * trace can be tens of thousands of characters and a request payload can be an
 * entire upload. Refusing outright would lose the alert, so the values are
 * clamped in prepareForValidation() and the rules below are the backstop — max
 * on a field that has already been trimmed can only fail if the trimming
 * missed, which is exactly when it should fail loudly.
 *
 * Only `fingerprint` and `message` are required. Everything else is genuinely
 * optional: a console exception has no URL and no front-end model, an internal
 * job has no organisation, and an intake that rejected those would drop the
 * errors that are hardest to reproduce.
 */
class ExceptionWebhookRequest extends FormRequest
{
    /** A trace longer than this is a wall, not information. */
    private const MAX_TRACE = 20000;

    private const MAX_BLOB = 10000;

    private const MAX_MESSAGE = 2000;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            // The identity of the error. Hex because the sender builds it with
            // hash('sha256', ...) — anything else is not a fingerprint from a
            // system we know, and it is the key everything else groups on.
            'fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
            'message' => ['required', 'string', 'max:' . self::MAX_MESSAGE],

            // Which server. Bounded to the column, not to a whitelist: a new
            // server should be able to report on its first day.
            'server_name' => ['nullable', 'string', 'max:100'],

            'url' => ['nullable', 'string', 'max:2000'],
            'method' => ['nullable', 'string', 'max:20'],
            'front_end_model' => ['nullable', 'string', 'max:255'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'string', 'max:100'],
            'organization_id' => ['nullable'],

            // How many times the sender has seen this error. Its count, not
            // ours — see the exception_count column comment.
            'occurrences' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'max:30'],

            'trace' => ['nullable', 'string', 'max:' . self::MAX_TRACE],
            'payload' => ['nullable', 'string', 'max:' . self::MAX_BLOB],
            'user_details' => ['nullable', 'string', 'max:' . self::MAX_BLOB],

            'alert_id' => ['nullable', 'integer'],
            'alert_url' => ['nullable', 'string', 'max:500'],

            'is_duplicate' => ['nullable', 'boolean'],
            'was_reopened' => ['nullable', 'boolean'],
            'occurred_at' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * Clamp the unbounded fields before they are validated.
     *
     * A trace that runs long is truncated rather than refused, because the
     * first frames are the ones that matter and losing the alert entirely to
     * keep the last two hundred is a bad trade. The cut is marked so nobody
     * reading the ticket mistakes a truncated trace for a short one.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => $this->trim($this->input('message'), self::MAX_MESSAGE),
            'trace' => $this->trim($this->input('trace'), self::MAX_TRACE),
            'payload' => $this->trim($this->input('payload'), self::MAX_BLOB),
            'user_details' => $this->trim($this->input('user_details'), self::MAX_BLOB),
        ]);
    }

    private function trim(mixed $value, int $max): ?string
    {
        if (! is_string($value) || $value === '') {
            return is_string($value) ? $value : null;
        }

        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 20) . "\n… [اتقص]";
    }
}
