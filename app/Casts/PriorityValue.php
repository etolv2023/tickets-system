<?php

namespace App\Casts;

use App\Models\PriorityDefinition;

/**
 * What the old fixed Priority enum used to be, now backed by the priorities
 * table. Same design as TicketStatusValue: a singleton-per-key value object,
 * not a native enum, because the case list is no longer fixed at compile time.
 */
final class PriorityValue
{
    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(
        public readonly string $value,
        private readonly string $label,
        private readonly string $colorVariant,
        private readonly int $hours,
        public readonly bool $isSystem,
        private readonly int $rank,
        private readonly int $total,
    ) {
    }

    public static function for(string $key): self
    {
        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $map = PriorityDefinition::map();
        $row = $map[$key] ?? null;

        /* map() is ordered by position, so the offset IS the rank. A key that
         * isn't in the list at all (a deleted priority still on an old ticket)
         * gets the middle rank rather than 0 — false from array_search would
         * otherwise coerce to 0 and paint a stale row as the most urgent. */
        $offset = array_search($key, array_keys($map), true);
        $total = max(1, count($map));

        return self::$instances[$key] = new self(
            value: $key,
            label: $row->name_ar ?? $key,
            colorVariant: $row->color ?? 'slate',
            hours: $row->sla_hours ?? 24,
            isSystem: $row->is_system ?? false,
            rank: $offset === false ? intdiv($total, 2) : $offset,
            total: $total,
        );
    }

    /** Clears the singleton cache — the admin priorities screen needs this after editing one. */
    public static function forget(): void
    {
        self::$instances = [];
    }

    public function label(): string
    {
        return $this->label;
    }

    public function variant(): string
    {
        return $this->colorVariant;
    }

    public function slaHours(): int
    {
        return $this->hours;
    }

    /**
     * The <x-icon> name for this priority — a direction, not a colour.
     *
     * Keyed off the row's rank in the ordered list rather than off its key, so
     * a priority an admin invents ("حرج", "مؤجل") still gets the right arrow.
     * The stripe already carries the hue; this is what makes priority legible
     * in greyscale and to a colour-blind reader.
     */
    public function icon(): string
    {
        if ($this->rank === 0) {
            return 'priority-urgent';
        }

        if ($this->rank === $this->total - 1) {
            return 'priority-low';
        }

        return $this->rank < $this->total / 2 ? 'priority-high' : 'priority-medium';
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
