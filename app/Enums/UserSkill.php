<?php

namespace App\Enums;

/**
 * What a user can be assigned, independent of their role. A "both" developer
 * shows up in the frontend AND backend assignment lists. F00.3
 */
enum UserSkill: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';
    case Both = 'both';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Frontend => 'فرونت',
            self::Backend => 'باك',
            self::Both => 'فرونت وباك',
            self::None => 'لا ينطبق',
        };
    }

    public function coversFrontend(): bool
    {
        return $this === self::Frontend || $this === self::Both;
    }

    public function coversBackend(): bool
    {
        return $this === self::Backend || $this === self::Both;
    }

    /** @return array<string, string> value => Arabic label, for select inputs. */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $case) => $carry + [$case->value => $case->label()],
            []
        );
    }
}
