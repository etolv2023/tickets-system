<?php

namespace App\Enums;

enum TicketType: string
{
    case Bug = 'bug';
    case Inquiry = 'inquiry';
    case Feature = 'feature';
    case NewModule = 'new_module';
    case Undefined = 'undefined';

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'بج',
            self::Inquiry => 'استفسار',
            self::Feature => 'فيتشر',
            self::NewModule => 'موديول جديد',
            self::Undefined => 'غير محدد',
        };
    }

    /** Badge variant — the type palette from CLAUDE.md § 6. */
    public function variant(): string
    {
        return match ($this) {
            self::Bug => 'bug',
            self::Inquiry => 'inquiry',
            self::Feature => 'feature',
            self::NewModule => 'module',
            self::Undefined => 'neutral',
        };
    }

    /** F15: features and new modules can't be assigned before an admin approves. */
    public function needsApproval(): bool
    {
        return $this === self::Feature || $this === self::NewModule;
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
