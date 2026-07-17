<?php

namespace App\Enums;

/**
 * Ticket priority. Rendered as a 3px stripe, never as a status badge —
 * the two must not fight over colour (CLAUDE.md § 6).
 */
enum Priority: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::Urgent => 'عاجل',
            self::High => 'مرتفع',
            self::Medium => 'متوسط',
            self::Low => 'منخفض',
        };
    }

    /** Settings key holding this priority's SLA window in hours. F22.2 */
    public function slaSettingKey(): string
    {
        return "sla_hours_{$this->value}";
    }

    /** Default SLA window in hours, used to seed settings. PLAN.md § 12 #5 */
    public function defaultSlaHours(): int
    {
        return match ($this) {
            self::Urgent => 4,
            self::High => 24,
            self::Medium => 72,
            self::Low => 168,
        };
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
