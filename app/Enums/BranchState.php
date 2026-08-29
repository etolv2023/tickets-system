<?php

namespace App\Enums;

/**
 * Whether the branch is still on GitHub — F27.
 *
 * Two cases and no third, because this table never forgets: a branch is either
 * there, or it was there and is not any more. There is no "removed from our
 * records" state to represent, since no code path removes a row.
 */
enum BranchState: string
{
    case Active = 'active';

    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'موجود',
            self::Deleted => 'اتمسح من جيت هب',
        };
    }

    /** A badge hue from the semantic palette (CLAUDE.md § 6). */
    public function variant(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Deleted => 'slate',
        };
    }
}
