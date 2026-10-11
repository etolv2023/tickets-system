<?php

namespace App\Casts;

use App\Models\LinkTypeDefinition;

/**
 * What the old fixed LinkType enum used to be, now backed by the link_types
 * table. Singleton-per-key value object, same design as TicketTypeValue.
 *
 * Keeps the surface every callsite used — label(), inverseLabel(), variant(),
 * ->value — plus isBlocks() so the one behavioural key (the blocked marker)
 * stays checkable without a magic string.
 */
final class LinkTypeValue
{
    /** @var array<string, self> */
    private static array $instances = [];

    private function __construct(
        public readonly string $value,
        private readonly string $label,
        private readonly ?string $labelEn,
        private readonly string $inverseLabel,
        private readonly ?string $inverseLabelEn,
        private readonly string $colorVariant,
    ) {
    }

    public static function for(string $key): self
    {
        if (isset(self::$instances[$key])) {
            return self::$instances[$key];
        }

        $row = LinkTypeDefinition::map()[$key] ?? null;

        return self::$instances[$key] = new self(
            value: $key,
            label: $row->name_ar ?? $key,
            labelEn: filled($row?->name_en) ? $row->name_en : null,
            inverseLabel: $row->inverse_label_ar ?? ($row->name_ar ?? $key),
            inverseLabelEn: filled($row?->inverse_label_en) ? $row->inverse_label_en : (filled($row?->name_en) ? $row->name_en : null),
            colorVariant: $row->color ?? 'neutral',
        );
    }

    public static function forget(): void
    {
        self::$instances = [];
    }

    /** How it reads from the source ticket. */
    public function label(): string
    {
        return app()->isLocale('en') && $this->labelEn !== null ? $this->labelEn : $this->label;
    }

    /** How the same row reads from the other ticket. F10 */
    public function inverseLabel(): string
    {
        return app()->isLocale('en') && $this->inverseLabelEn !== null ? $this->inverseLabelEn : $this->inverseLabel;
    }

    public function variant(): string
    {
        return $this->colorVariant;
    }

    /** The one type that raises the blocked marker (F10/F22). */
    public function isBlocks(): bool
    {
        return $this->value === 'blocks';
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
