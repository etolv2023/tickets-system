<?php

namespace App\Enums;

/**
 * Ticket relationships (F10). Stored once, in one direction; the display shows
 * both sides of the pair.
 */
enum LinkType: string
{
    case Blocks = 'blocks';
    case Relates = 'relates';
    case Duplicates = 'duplicates';
    case CausedBy = 'caused_by';

    /** How it reads from the source ticket. */
    public function label(): string
    {
        return match ($this) {
            self::Blocks => 'بتبلوك',
            self::Relates => 'مرتبطة بـ',
            self::Duplicates => 'مكررة لـ',
            self::CausedBy => 'سببها',
        };
    }

    /** How the SAME row reads from the other ticket. F10 */
    public function inverseLabel(): string
    {
        return match ($this) {
            self::Blocks => 'مبلوكة بـ',
            self::Relates => 'مرتبطة بـ',
            self::Duplicates => 'متكررة في',
            self::CausedBy => 'تسببت في',
        };
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
