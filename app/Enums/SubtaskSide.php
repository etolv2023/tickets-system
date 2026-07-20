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
    case Devops = 'devops';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Frontend => 'فرونت',
            self::Backend => 'باك',
            self::Qa => 'اختبار',
            self::Support => 'دعم',
            self::Devops => 'ديف أوبس',
            self::Other => 'أخرى',
        };
    }

    /**
     * Only these two gate a work log's "خلصت". F07
     *
     * Devops is deliberately not one of them: it holds no work log, so there is
     * nothing for it to block. An open devops subtask still stops the TICKET
     * reaching resolved — subtaskBlocker() counts every side — but it does not
     * stop the frontend or the backend declaring their own work finished.
     */
    public function blocksWorkLog(): bool
    {
        return $this === self::Frontend || $this === self::Backend;
    }

    /**
     * F18: which earning side a Done subtask on this side counts as. 'other'
     * maps to null — a subtask nobody classified is not evidence of whose
     * work it was, so it never earns points.
     */
    public function toPointSide(): ?PointSide
    {
        return match ($this) {
            self::Frontend => PointSide::Frontend,
            self::Backend => PointSide::Backend,
            self::Qa => PointSide::Tester,
            self::Support => PointSide::Support,
            self::Devops => PointSide::Devops,
            self::Other => null,
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
