<?php

namespace App\Enums;

enum SubtaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'مستنية',
            self::InProgress => 'جاري العمل',
            self::Done => 'خلصت',
            self::Blocked => 'متوقفة',
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::Done => 'resolved',
            self::InProgress => 'inquiry',
            self::Blocked => 'blocked',
            self::Todo => 'neutral',
        };
    }

    /** F08: a blocked subtask must say why. */
    public function needsReason(): bool
    {
        return $this === self::Blocked;
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
