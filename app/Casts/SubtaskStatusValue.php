<?php

namespace App\Casts;

use App\Models\SubtaskStatusDefinition;

/**
 * What the old fixed SubtaskStatus enum used to be, now backed by the
 * subtask_statuses table. Singleton-per-key value object, same design as
 * TicketStatusValue / TicketTypeValue.
 *
 * Keeps the surface every callsite used — label(), variant(), needsReason(),
 * ->value — plus isDone()/isInProgress()/isBlocked() so the behavioural keys
 * (which drive counters, points and started_at) stay checkable without magic
 * strings. Those three keys are fixed system rows, so the checks are stable.
 */
final class SubtaskStatusValue
{
    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(
        public readonly string $value,
        private readonly string $label,
        private readonly string $colorVariant,
        private readonly bool $needsReason,
    ) {
    }

    public static function for(string $key): self
    {
        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $row = SubtaskStatusDefinition::map()[$key] ?? null;

        return self::$instances[$key] = new self(
            value: $key,
            label: $row->name_ar ?? $key,
            colorVariant: $row->color ?? 'neutral',
            needsReason: (bool) ($row->needs_reason ?? false),
        );
    }

    /** Clears the singleton cache — the admin statuses screen needs this after editing one. */
    public static function forget(): void
    {
        self::$instances = [];
    }

    public function label(): string
    {
        return $this->label;
    }

    /** Badge variant — the palette colour stored on the row. */
    public function variant(): string
    {
        return $this->colorVariant;
    }

    /** F08: a status flagged this way makes the subtask require a reason (blocked). */
    public function needsReason(): bool
    {
        return $this->needsReason;
    }

    /** The one status that means finished — counters and points key off it. */
    public function isDone(): bool
    {
        return $this->value === 'done';
    }

    /** Setting this stamps started_at. */
    public function isInProgress(): bool
    {
        return $this->value === 'in_progress';
    }

    public function isBlocked(): bool
    {
        return $this->value === 'blocked';
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
