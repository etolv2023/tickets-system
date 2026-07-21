<?php

namespace App\Enums;

/** Which side of the codebase the work lands on. Drives the points matrix (F18). */
enum TicketScope: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';
    case Both = 'both';
    case Devops = 'devops';
    case Inquiry = 'inquiry';
    case Undefined = 'undefined';

    public function label(): string
    {
        return match ($this) {
            self::Frontend => 'فرونت',
            self::Backend => 'باك',
            self::Both => 'فرونت وباك',
            // A ticket whose work is entirely devops (infra/deploy) — not
            // frontend or backend. It drives the points matrix like any other
            // scope: subtask points come from (type, 'devops', side). F18/2026-07-21.
            self::Devops => 'ديف أوبس',
            self::Inquiry => 'استفسار',
            self::Undefined => 'غير محدد',
        };
    }

    public function needsFrontend(): bool
    {
        return $this === self::Frontend || $this === self::Both;
    }

    public function needsBackend(): bool
    {
        return $this === self::Backend || $this === self::Both;
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
