<?php

namespace App\Notifications;

/**
 * Every kind of thing the system tells someone about.
 *
 * One catalogue instead of a string literal at each call site. Before this the
 * event name was typed out wherever a notification was sent, which is why the
 * ledger held both 'ticket.status' and 'ticket.status_changed' for the same
 * thing, and why nothing could group or filter reliably.
 *
 * Each case carries how it should read: its group, its icon, and whether it is
 * important enough to be worth an email.
 */
enum NotificationEvent: string
{
    // The ticket itself
    case TicketCreated = 'ticket.created';
    case TicketAssigned = 'ticket.assigned';
    case TicketStatusChanged = 'ticket.status_changed';
    case TicketApproved = 'ticket.approved';
    case TicketRejected = 'ticket.rejected';
    case TicketReopened = 'ticket.reopened';
    case TicketResolved = 'ticket.resolved';
    case TicketClosed = 'ticket.closed';
    case TicketUpdated = 'ticket.updated';
    case TicketDueSoon = 'ticket.due_soon';
    case TicketSlaBreached = 'ticket.sla_breached';
    case ClientNotified = 'ticket.client_notified';
    case PortalPasswordReset = 'ticket.portal_password_regenerated';

    // The conversation
    case CommentAdded = 'comment.added';
    case CommentFromClient = 'comment.from_client';
    case MentionedInComment = 'comment.mentioned';

    // The work
    case SubtaskAssigned = 'subtask.assigned';
    case SubtaskCompleted = 'subtask.completed';
    case SubtaskBlocked = 'subtask.blocked';
    case SubtaskDueToday = 'subtask.due_today';
    case TimeLogged = 'time.logged';

    // Relationships and judgement
    case TicketLinked = 'link.created';
    case BlockerResolved = 'link.blocker_resolved';
    case RatingGiven = 'rating.given';
    case PointsAwarded = 'points.awarded';

    /** The heading a notification sits under in the list. */
    public function group(): string
    {
        return match ($this) {
            self::TicketCreated, self::TicketAssigned, self::TicketUpdated => 'التوزيع',
            self::TicketStatusChanged, self::TicketApproved, self::TicketRejected,
            self::TicketReopened, self::TicketResolved, self::TicketClosed => 'الحالة',
            self::CommentAdded, self::CommentFromClient, self::MentionedInComment => 'المحادثة',
            self::SubtaskAssigned, self::SubtaskCompleted, self::SubtaskBlocked,
            self::SubtaskDueToday, self::TimeLogged => 'الشغل',
            self::TicketDueSoon, self::TicketSlaBreached => 'المهل',
            self::TicketLinked, self::BlockerResolved => 'الروابط',
            self::RatingGiven, self::PointsAwarded => 'التقييم والنقاط',
            self::ClientNotified, self::PortalPasswordReset => 'العميل',
        };
    }

    /** An <x-icon> name. */
    public function icon(): string
    {
        return match ($this) {
            self::CommentAdded, self::CommentFromClient, self::MentionedInComment => 'ticket',
            self::SubtaskAssigned, self::SubtaskCompleted, self::SubtaskDueToday => 'check-circle',
            self::SubtaskBlocked, self::TicketSlaBreached => 'alert',
            self::TicketDueSoon => 'timer',
            self::TimeLogged => 'timer',
            self::TicketLinked, self::BlockerResolved => 'tag',
            self::RatingGiven, self::PointsAwarded => 'star',
            self::TicketApproved, self::TicketResolved, self::TicketClosed => 'check',
            self::TicketRejected => 'close',
            default => 'bell',
        };
    }

    /** Colours the dot. Only the ones that mean something get a hue. */
    public function variant(): string
    {
        return match ($this) {
            self::TicketSlaBreached, self::SubtaskBlocked, self::TicketRejected => 'red',
            self::TicketDueSoon, self::SubtaskDueToday, self::CommentFromClient => 'amber',
            self::TicketResolved, self::TicketClosed, self::TicketApproved,
            self::SubtaskCompleted, self::BlockerResolved, self::PointsAwarded => 'green',
            // Cyan, not blue — blue became the interaction hue (2026-07-19)
            // and a notification's category dot is information, not a control.
            self::TicketAssigned, self::SubtaskAssigned, self::MentionedInComment => 'cyan',
            default => 'slate',
        };
    }

    /**
     * Whether this is worth an email.
     *
     * Emailing every event trains people to filter the whole address into a
     * folder they never open. Only things that need action off-screen qualify;
     * the rest live in the bell.
     */
    public function emailWorthy(): bool
    {
        return match ($this) {
            self::TicketAssigned, self::SubtaskAssigned, self::MentionedInComment,
            self::TicketSlaBreached, self::TicketRejected, self::CommentFromClient,
            self::TicketApproved => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TicketCreated => 'تذكرة جديدة',
            self::TicketAssigned => 'اتعملك أساين',
            self::TicketStatusChanged => 'الحالة اتغيرت',
            self::TicketApproved => 'اتوافق عليها',
            self::TicketRejected => 'اترفضت',
            self::TicketReopened => 'اترجعت',
            self::TicketResolved => 'اتحلت',
            self::TicketClosed => 'اتقفلت',
            self::TicketUpdated => 'اتعدلت',
            self::TicketDueSoon => 'المهلة قربت',
            self::TicketSlaBreached => 'خرقت المهلة',
            self::ClientNotified => 'العميل اتبلغ',
            self::PortalPasswordReset => 'باسورد البوابة اتغير',
            self::CommentAdded => 'تعليق جديد',
            self::CommentFromClient => 'رد من العميل',
            self::MentionedInComment => 'اتذكر اسمك',
            self::SubtaskAssigned => 'صب تاسك اتعملك',
            self::SubtaskCompleted => 'صب تاسك خلصت',
            self::SubtaskBlocked => 'صب تاسك اتبلوكت',
            self::SubtaskDueToday => 'صب تاسك مستحقة',
            self::TimeLogged => 'وقت اتسجل',
            self::TicketLinked => 'ربط جديد',
            self::BlockerResolved => 'البلوكر اتحل',
            self::RatingGiven => 'تقييم جديد',
            self::PointsAwarded => 'نقاط اتصرفت',
        };
    }

    /** @return array<string, string> for a filter dropdown */
    public static function groupOptions(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $groups[$case->group()] = $case->group();
        }

        return $groups;
    }
}
