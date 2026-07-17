<?php

namespace App\Enums;

/**
 * Wider than WorkSide on purpose: a subtask can be qa or support work, but only
 * frontend and backend are commitments that move the ticket (F07/F08).
 */
enum SubtaskSide: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';
    case Qa = 'qa';
    case Support = 'support';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Frontend => 'فرونت',
            self::Backend => 'باك',
            self::Qa => 'اختبار',
            self::Support => 'دعم',
            self::Other => 'أخرى',
        };
    }

    /** Only these two gate a work log's "خلصت". F07 */
    public function blocksWorkLog(): bool
    {
        return $this === self::Frontend || $this === self::Backend;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            []
        );
    }
}
