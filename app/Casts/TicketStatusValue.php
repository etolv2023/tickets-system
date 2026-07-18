<?php

namespace App\Casts;

use App\Models\TicketStatusDefinition;

/**
 * What TicketStatus (the old fixed enum) used to be, now backed by the
 * ticket_statuses table instead of a hardcoded set of cases.
 *
 * A native PHP enum can't have a dynamic case list, so this is a plain class
 * instead — but it deliberately keeps every enum-shaped call site working
 * unchanged (`$ticket->status->label()`, `->variant()`, `->value`), and keeps
 * identity comparison (`===`) meaningful the same way an enum's did: for()
 * always returns the exact same instance for a given key, so
 * `TicketStatusValue::for('resolved') === $ticket->status` is true whenever
 * both sides name the same status — the same guarantee TicketStatus::Resolved
 * gave for free.
 */
final class TicketStatusValue
{
    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(
        public readonly string $value,
        private readonly string $label,
        private readonly string $colorVariant,
        private readonly bool $open,
        public readonly bool $isSystem,
    ) {
    }

    public static function for(string $key): self
    {
        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $row = TicketStatusDefinition::map()[$key] ?? null;

        // A key with no matching row (a custom status that was since deleted,
        // but still named in an old ticket_status_history row) renders as a
        // plain, readable fallback rather than throwing — history must stay
        // viewable even after its status is gone.
        return self::$instances[$key] = new self(
            value: $key,
            label: $row->name_ar ?? $key,
            colorVariant: $row->color ?? 'slate',
            open: $row->is_open ?? true,
            isSystem: $row->is_system ?? false,
        );
    }

    /** Clears the singleton cache — tests and the admin CRUD screen need this after editing a status. */
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

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
