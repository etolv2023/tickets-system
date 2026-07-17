<?php

namespace App\Enums;

/**
 * The ticket lifecycle (PLAN.md § 3). Transitions are enforced by
 * TicketWorkflowService in phase 3, not here — this only describes the states.
 */
enum TicketStatus: string
{
    case New = 'new';
    case PendingApproval = 'pending_approval';
    case Rejected = 'rejected';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case DevDone = 'dev_done';
    case Testing = 'testing';
    case Reopened = 'reopened';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديدة',
            self::PendingApproval => 'بانتظار الموافقة',
            self::Rejected => 'مرفوضة',
            self::Assigned => 'موزعة',
            self::InProgress => 'جاري العمل',
            self::DevDone => 'تم التطوير',
            self::Testing => 'تحت الاختبار',
            self::Reopened => 'مرتجعة',
            self::Resolved => 'تم الحل',
            self::Closed => 'مغلقة',
        };
    }

    /**
     * Status badges deliberately avoid the priority palette so the two can sit
     * on the same card without competing (CLAUDE.md § 6).
     */
    public function variant(): string
    {
        return match ($this) {
            self::Resolved, self::Closed => 'resolved',
            self::Rejected, self::Reopened => 'blocked',
            self::InProgress, self::Testing => 'inquiry',
            default => 'neutral',
        };
    }

    /** Open means "still owed to the customer" — drives the age column. F03.1 */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed, self::Rejected], true);
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
