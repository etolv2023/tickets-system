<?php

namespace App\Enums;

/**
 * Who can earn on a ticket (F18). Wider than WorkSide: support and the tester
 * earn too, they just don't hold work logs.
 */
enum PointSide: string
{
    case Support = 'support';
    case Frontend = 'frontend';
    case Backend = 'backend';
    case Tester = 'tester';
    case Devops = 'devops';

    public function label(): string
    {
        return match ($this) {
            self::Support => 'دعم',
            self::Frontend => 'فرونت',
            self::Backend => 'باك',
            self::Tester => 'تيستر',
            self::Devops => 'ديف أوبس',
        };
    }

    /**
     * The role key this side corresponds to, or null for `support` (which named
     * the ticket's creator, never a role assignment). Since the fixed columns
     * were dropped (2026-07-24), a side maps to a role rather than a column.
     */
    public function roleKey(): ?string
    {
        return match ($this) {
            self::Support => null,
            self::Frontend => 'frontend',
            self::Backend => 'backend',
            self::Tester => 'tester',
            self::Devops => 'devops',
        };
    }

    /**
     * Which subtask sides count as work on this earning side.
     *
     * qa maps to tester, and 'other' maps to nothing: a subtask nobody
     * classified is not evidence of who did which side's work.
     *
     * @return array<int, string>
     */
    public function subtaskSides(): array
    {
        return match ($this) {
            self::Support => [SubtaskSide::Support->value],
            self::Frontend => [SubtaskSide::Frontend->value],
            self::Backend => [SubtaskSide::Backend->value],
            self::Tester => [SubtaskSide::Qa->value],
            self::Devops => [SubtaskSide::Devops->value],
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
