<?php

namespace App\Support;

use App\Casts\LinkTypeValue;
use App\Casts\SubtaskStatusValue;
use App\Casts\TicketStatusValue;
use App\Models\Role;

/**
 * A sentence stored as a translation key plus its parameters, rendered in
 * whatever language the reader is using when it is read.
 *
 * Notifications used to be saved as a finished Arabic sentence, so the English
 * UI showed Arabic forever. A parameter that is itself a translatable name (a
 * status, a role…) is stored as a reference and named at render time too;
 * everything else (ticket numbers, titles, people) is user content and is
 * shown exactly as it was written.
 */
final class Phrase
{
    /** @param array<string, scalar|array{ref: string, key: string|int}> $params */
    public function __construct(
        public readonly string $key,
        public readonly array $params = [],
    ) {
    }

    /** A parameter that names a ticket status, resolved when rendered. */
    public static function status(string $key): array
    {
        return ['ref' => 'ticket_status', 'key' => $key];
    }

    public static function subtaskStatus(string $key): array
    {
        return ['ref' => 'subtask_status', 'key' => $key];
    }

    public static function role(int $id): array
    {
        return ['ref' => 'role', 'key' => $id];
    }

    public static function linkType(string $key, bool $inverse = false): array
    {
        return ['ref' => $inverse ? 'link_type_inverse' : 'link_type', 'key' => $key];
    }

    /** Another phrase used as a parameter (e.g. a system note quoted in a message). */
    public static function nested(self $phrase): array
    {
        return ['ref' => 'phrase', 'key' => $phrase->key, 'params' => $phrase->params];
    }

    /** A fixed phrase used as a parameter (e.g. "no reason given"). */
    public static function text(string $key): array
    {
        return ['ref' => 'text', 'key' => $key];
    }

    public function render(?string $locale = null): string
    {
        $replace = [];

        foreach ($this->params as $name => $value) {
            $replace[$name] = is_array($value) ? self::resolve($value, $locale) : (string) $value;
        }

        return __($this->key, $replace, $locale);
    }

    /** @return array{key: string, params: array<string, mixed>} */
    public function toArray(): array
    {
        return ['key' => $this->key, 'params' => $this->params];
    }

    /** For a plain string column (status-history notes): JSON with a marker. */
    public function toStored(): string
    {
        return json_encode(['phrase' => $this->key, 'params' => $this->params], JSON_UNESCAPED_UNICODE);
    }

    /** A stored column value: a phrase written by the system, or text a person typed. */
    public static function fromStored(?string $value): ?string
    {
        if ($value === null || ! str_starts_with($value, '{"phrase":')) {
            return $value;
        }

        $data = json_decode($value, true);

        return is_array($data) && isset($data['phrase'])
            ? (new self($data['phrase'], $data['params'] ?? []))->render()
            : $value;
    }

    /**
     * The text of a stored notification payload: the phrase when the row has
     * one, otherwise the sentence saved before phrases existed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromNotification(array $data): string
    {
        if (isset($data['message_key'])) {
            return (new self($data['message_key'], $data['message_params'] ?? []))->render();
        }

        return (string) ($data['message'] ?? '');
    }

    /** @param array{ref: string, key: string|int} $ref */
    private static function resolve(array $ref, ?string $locale): string
    {
        $render = fn () => match ($ref['ref'] ?? null) {
            'ticket_status' => TicketStatusValue::for((string) $ref['key'])->label(),
            'subtask_status' => SubtaskStatusValue::for((string) $ref['key'])->label(),
            'link_type' => LinkTypeValue::for((string) $ref['key'])->label(),
            'link_type_inverse' => LinkTypeValue::for((string) $ref['key'])->inverseLabel(),
            'role' => Role::byId()->get((int) $ref['key'])?->label() ?? '—',
            'text' => __((string) $ref['key']),
            'phrase' => (new self((string) $ref['key'], $ref['params'] ?? []))->render(),
            default => (string) ($ref['key'] ?? ''),
        };

        return $locale === null ? $render() : Locale::as($locale, $render);
    }
}
