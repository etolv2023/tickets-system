<?php

namespace App\Casts;

use App\Models\TicketTypeDefinition;

/**
 * What the old fixed TicketType enum used to be, now backed by the ticket_types
 * table. Same design as PriorityValue / TicketStatusValue: a singleton-per-key
 * value object, not a native enum, because the case list is no longer fixed at
 * compile time.
 *
 * Keeps the exact surface every callsite already used — label(), variant(),
 * icon(), needsApproval(), ->value — so nothing that reads $ticket->type has to
 * change.
 */
final class TicketTypeValue
{
    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(
        public readonly string $value,
        private readonly string $label,
        private readonly string $colorVariant,
        private readonly string $iconName,
        private readonly bool $needsApproval,
        private readonly float $defaultPoints,
    ) {
    }

    public static function for(string $key): self
    {
        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $row = TicketTypeDefinition::map()[$key] ?? null;

        return self::$instances[$key] = new self(
            value: $key,
            label: $row->name_ar ?? $key,
            colorVariant: $row->color ?? 'neutral',
            // A type deleted while still on old tickets falls back to the
            // neutral "undefined" glyph rather than rendering nothing.
            iconName: $row->icon ?? 'undefined',
            needsApproval: (bool) ($row->needs_approval ?? false),
            // A type deleted out from under old tickets falls back to the flat
            // 1.0 the whole system used before types carried a weight.
            defaultPoints: (float) ($row->default_points ?? 1.0),
        );
    }

    /** Clears the singleton cache — the admin types screen needs this after editing one. */
    public static function forget(): void
    {
        self::$instances = [];
    }

    public function label(): string
    {
        return $this->label;
    }

    /** Badge variant — the palette colour stored on the row (§ 6). */
    public function variant(): string
    {
        return $this->colorVariant;
    }

    /** The <x-icon> name for this type. */
    public function icon(): string
    {
        return $this->iconName;
    }

    /** F15: features and new modules (and any admin-flagged type) can't be assigned before approval. */
    public function needsApproval(): bool
    {
        return $this->needsApproval;
    }

    /**
     * ★ (2026-08-02) The points a new subtask on this type opens at. Still a
     * starting number, not a formula — SubtaskService applies it once at
     * creation and it is hand-editable from then on (F18).
     */
    public function defaultPoints(): float
    {
        return $this->defaultPoints;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
