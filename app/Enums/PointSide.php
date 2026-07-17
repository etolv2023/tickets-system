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

    public function label(): string
    {
        return match ($this) {
            self::Support => 'دعم',
            self::Frontend => 'فرونت',
            self::Backend => 'باك',
            self::Tester => 'تيستر',
        };
    }

    /** The tickets column naming the person who earns on this side. */
    public function participantColumn(): string
    {
        return match ($this) {
            self::Support => 'created_by',
            self::Frontend => 'assigned_frontend_id',
            self::Backend => 'assigned_backend_id',
            self::Tester => 'tester_id',
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
