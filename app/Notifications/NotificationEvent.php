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
    /** Stable identifier of the group: stored on the row and used by the filter. */
    public function groupKey(): string
    {
        return match ($this) {
            self::TicketCreated, self::TicketAssigned, self::TicketUpdated => 'assignment',
            self::TicketStatusChanged, self::TicketApproved, self::TicketRejected,
            self::TicketReopened, self::TicketResolved, self::TicketClosed => 'status',
            self::CommentAdded, self::CommentFromClient, self::MentionedInComment => 'conversation',
            self::SubtaskAssigned, self::SubtaskCompleted, self::SubtaskBlocked,
            self::SubtaskDueToday, self::TimeLogged => 'work',
            self::TicketDueSoon, self::TicketSlaBreached => 'deadlines',
            self::TicketLinked, self::BlockerResolved => 'links',
            self::RatingGiven, self::PointsAwarded => 'ratings',
            self::ClientNotified, self::PortalPasswordReset => 'client',
        };
    }

    public function group(): string
    {
        return __(self::GROUP_LABELS[$this->groupKey()]);
    }

    /** @var array<string, string> group key => Arabic source label */
    private const GROUP_LABELS = [
        'assignment' => 'الإسناد',
        'status' => 'الحالة',
        'conversation' => 'المحادثة',
        'work' => 'العمل',
        'deadlines' => 'المواعيد',
        'links' => 'الروابط',
        'ratings' => 'التقييم والنقاط',
        'client' => 'العميل',
    ];

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
        return __(match ($this) {
            self::TicketCreated => 'تذكرة جديدة',
            self::TicketAssigned => 'تم إسنادك',
            self::TicketStatusChanged => 'تغيّرت الحالة',
            self::TicketApproved => 'تمت الموافقة',
            self::TicketRejected => 'تم الرفض',
            self::TicketReopened => 'أعيد فتحها',
            self::TicketResolved => 'تم الحل',
            self::TicketClosed => 'أُغلقت',
            self::TicketUpdated => 'تم التعديل',
            self::TicketDueSoon => 'اقترب الموعد',
            self::TicketSlaBreached => 'تجاوزت المهلة',
            self::ClientNotified => 'تم إبلاغ العميل',
            self::PortalPasswordReset => 'تغيّرت كلمة مرور البوابة',
            self::CommentAdded => 'تعليق جديد',
            self::CommentFromClient => 'رد من العميل',
            self::MentionedInComment => 'تمت الإشارة إليك',
            self::SubtaskAssigned => 'أُسندت إليك مهمة فرعية',
            self::SubtaskCompleted => 'اكتملت مهمة فرعية',
            self::SubtaskBlocked => 'توقفت مهمة فرعية',
            self::SubtaskDueToday => 'مهمة فرعية مستحقة',
            self::TimeLogged => 'تم تسجيل وقت',
            self::TicketLinked => 'ربط جديد',
            self::BlockerResolved => 'زال العائق',
            self::RatingGiven => 'تقييم جديد',
            self::PointsAwarded => 'تم احتساب النقاط',
        });
    }

    /** @return array<string, string> group key => label in the current language */
    public static function groupOptions(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $groups[$case->groupKey()] = $case->group();
        }

        return $groups;
    }

    /** @return array<int, string> the event values that belong to a group */
    public static function eventsIn(string $groupKey): array
    {
        return array_values(array_map(
            fn (self $case) => $case->value,
            array_filter(self::cases(), fn (self $case) => $case->groupKey() === $groupKey)
        ));
    }
}
