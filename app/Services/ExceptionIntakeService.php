<?php

namespace App\Services;

use App\Casts\PriorityValue;
use App\Casts\TicketTypeValue;
use App\Models\LinkTypeDefinition;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketLink;
use App\Models\TicketSubtask;
use App\Models\User;
use App\Models\UserLeave;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;
use RuntimeException;

/**
 * F26 — turns an uncaught error on another server into somebody's next four
 * hours of work.
 *
 * The whole rule lives here rather than in the controller, because it is a
 * rule and not a request: what "the same error" means, who picks it up, and
 * when it is due are decisions about tickets, and the sending system is
 * deliberately not allowed to make any of them. All it does is report facts.
 *
 * WHAT ARRIVES TWICE
 *
 * The sender groups errors by a fingerprint over the stack trace, so a query
 * that breaks four hundred times in an hour is one fingerprint, reported
 * repeatedly. Three outcomes, and the difference between them is only ever the
 * state of the last ticket that fingerprint produced:
 *
 *   no ticket yet          → open one
 *   last ticket still open → comment on it with the new count
 *   last ticket not open   → open a NEW one, linked to the old as «مكررة لـ»
 *
 * The third case is the one worth explaining. An error that comes back after
 * its ticket was resolved and closed is not an update to finished work — it is
 * evidence the fix did not hold, and it needs a live deadline and somebody's
 * name on it again. Commenting on a closed ticket would put that evidence
 * somewhere nobody is looking. Linking the new ticket to the old is what keeps
 * the history walkable: three recurrences over three months are three tickets
 * in a chain, and the chain is the story.
 *
 * "Not open" is `is_open` off the status row rather than a list of status keys
 * written here — the admin owns that list (F06.1), and a hardcoded copy would
 * be wrong the first time somebody adds a status.
 *
 * WHO GETS IT
 *
 * A random back-end developer who is actually available, never the same person
 * twice running. The exclusion is against the assignee of the last exception
 * ticket, not against some rota stored elsewhere: the ledger of who got what is
 * already the tickets, and a second record of it could disagree with them.
 *
 * "Available" is two conditions, and both matter for the same reason — this
 * ticket arrives with a four-hour deadline and a penalty attached, so handing
 * it to somebody who cannot possibly meet it is not a routing mistake, it is a
 * deduction against a person for being away:
 *
 *   is_active     off means the account is disabled. Assigning to one would
 *                 put work on somebody who cannot even log in.
 *   not on leave  an approved UserLeave covering today. Docking someone's
 *                 points for a deadline they slept through on annual leave is
 *                 the single worst thing this feature could do.
 *
 * Random rather than round-robin on purpose. Round-robin needs a cursor, and a
 * cursor is state that drifts the moment somebody joins, leaves, or takes
 * leave. Random-excluding-the-last is stateless, cannot drift, and delivers
 * the one property that was actually asked for — nobody gets two in a row.
 *
 * WHEN IT IS DUE
 *
 * Four WORKING hours, through SlaService, so the clock pauses overnight, at the
 * weekend and on holidays. An error at 4pm is due mid-morning tomorrow rather
 * than at 8pm tonight — a deadline that expires while the office is shut is a
 * deduction, not a deadline.
 *
 * The moment lands on the subtask's `due_at`, and F18.1 charges against it like
 * any other. That is the whole point of the type existing: an exception ticket
 * earns and loses points exactly as a planned task does, so it can be reported
 * on, priced, and paid alongside everything else.
 */
class ExceptionIntakeService
{
    /** Nothing is created for an error we cannot attribute to a ticket type. */
    private const LINK_TYPE = 'duplicates';

    public function __construct(
        private readonly TicketNumberService $numbers,
        private readonly SlaService $sla,
        private readonly TicketWorkflowService $workflow,
        private readonly TicketService $tickets,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  a validated ExceptionWebhookRequest
     * @return array{action: string, ticket_number: string|null, ticket_id: int|null}
     */
    public function handle(array $data): array
    {
        $fingerprint = (string) $data['fingerprint'];

        // Serialised on the fingerprint for the length of the decision. Two
        // servers reporting the same error in the same second is the normal
        // case during an outage, and without this both would read "no ticket
        // yet" and both would open one — two tickets, two assignees, two
        // deadlines, for one error.
        return DB::transaction(function () use ($data, $fingerprint) {
            $previous = Ticket::query()
                ->where('exception_fingerprint', $fingerprint)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($previous === null) {
                return $this->open($data, null);
            }

            if ($previous->status->isOpen()) {
                return $this->recur($previous, $data);
            }

            // Closed, resolved or rejected — the error outlived its ticket.
            return $this->open($data, $previous);
        });
    }

    /**
     * Open a ticket for this error and put somebody on it.
     *
     * $previous is the closed ticket this one supersedes, when there is one.
     */
    private function open(array $data, ?Ticket $previous): array
    {
        $type = TicketTypeValue::for((string) config('tickets.intake.type', 'exception'));
        $priority = PriorityValue::for((string) config('tickets.intake.priority', 'urgent'));

        $assignee = $this->pickAssignee();
        $reportedAt = CarbonImmutable::now();

        $ticket = Ticket::create([
            'ticket_number' => $this->numbers->next((int) $reportedAt->format('Y')),
            // No company: nobody's customer raised this, the software did. F25
            // reads a null company_id as internal work, which is what it is.
            'company_id' => null,
            'title' => $this->title($data),
            'description' => $this->description($data, $previous),
            'type' => $type,
            'priority' => $priority,
            // The URL that broke, when there was one. A console exception sends
            // "queue:log → SomeJob" here instead, which is still the answer to
            // "where did this happen".
            'page_url' => $this->truncate($data['url'] ?? null, 500),
            'exception_fingerprint' => (string) $data['fingerprint'],
            'exception_count' => max(1, (int) ($data['occurrences'] ?? 1)),
            'exception_server' => $this->serverName($data),
            'status' => \App\Casts\TicketStatusValue::for('new'),
            'approval_status' => 'not_required',
            // Attributed to whoever it is assigned to rather than to a fake
            // system account: created_by is a foreign key to a real user, and
            // inventing a "robot" row would put a person who does not exist on
            // every report that groups by creator. The description says plainly
            // where the ticket came from.
            'created_by' => $assignee->id,
            'reported_at' => $reportedAt,
            'sla_due_at' => $this->sla->dueAt($priority, $reportedAt),
        ]);

        $ticket->generatePortalPassword();

        // Assign through the normal path so the starter subtask, the work log
        // and the notification all happen exactly as they do for a human
        // assignment (F06.3) — and so F18 sees an ordinary subtask to pay.
        $roleId = $this->assignRoleId();
        $this->workflow->assign($ticket, [$roleId => $assignee->id], $assignee->id);

        $this->setDeadline($ticket, $roleId, $reportedAt);

        if ($previous !== null) {
            $this->linkToPrevious($ticket, $previous, $assignee->id);
        }

        return [
            'action' => $previous === null ? 'created' : 'recreated',
            'ticket_number' => $ticket->ticket_number,
            'ticket_id' => $ticket->id,
        ];
    }

    /**
     * The error happened again while its ticket is still open.
     *
     * A comment, and a bump to the stored count. Deliberately nothing else: the
     * assignee does not change, the deadline does not move, and no second
     * penalty is possible — the person already holding it is not made later by
     * the software failing again on its own.
     */
    private function recur(Ticket $ticket, array $data): array
    {
        $count = max((int) $ticket->exception_count, (int) ($data['occurrences'] ?? 1));

        $ticket->forceFill([
            'exception_count' => $count,
            // The most recent server to report it. Errors move between servers
            // and the newest sighting is the useful one.
            'exception_server' => $this->serverName($data) ?: $ticket->exception_server,
        ])->save();

        $seen = $this->serverName($data) ?: 'غير معروف';
        $when = now()->translatedFormat('j M Y - H:i');

        // Internal: this is operational noise for the team, not something a
        // client on the portal has any use for.
        $ticket->comments()->create([
            'user_id' => $ticket->created_by,
            'body' => Purifier::clean(
                '<p>الاكسبشن ده حصل تاني — إجمالي المرات: <strong>' . $count . '</strong>'
                . '<br>آخر مرة: ' . e($when) . ' من سيرفر <strong>' . e($seen) . '</strong></p>'
            ),
            'is_internal' => true,
        ]);

        return [
            'action' => 'commented',
            'ticket_number' => $ticket->ticket_number,
            'ticket_id' => $ticket->id,
        ];
    }

    /**
     * Put the four-working-hour deadline on the subtask the assignment created.
     *
     * Both columns are written, and they are not redundant. `due_at` is the
     * promise F18.1 charges against; `due_date` is the day it falls on, and
     * every existing screen — the calendar, the board, «على دماغي» — filters
     * and groups on that one. Writing only the exact moment would have made an
     * exception subtask invisible on the calendar it is most urgent in.
     */
    private function setDeadline(Ticket $ticket, int $roleId, CarbonImmutable $from): void
    {
        $hours = (float) config('tickets.intake.due_working_hours', 4);

        $due = $this->sla->addWorkingHours($from, $hours);

        TicketSubtask::query()
            ->where('ticket_id', $ticket->id)
            ->where('role_id', $roleId)
            ->update([
                'due_at' => $due,
                'due_date' => $due->toDateString(),
                'start_date' => $from->toDateString(),
            ]);
    }

    /**
     * «مكررة لـ» — the new ticket points at the closed one it came back from.
     *
     * A missing link type is not worth losing the ticket over: the type list is
     * admin-owned and `duplicates` is a system row, but if somebody has managed
     * to remove it, an unlinked ticket that exists beats an exception that was
     * never recorded.
     */
    private function linkToPrevious(Ticket $ticket, Ticket $previous, int $actorId): void
    {
        if (! array_key_exists(self::LINK_TYPE, LinkTypeDefinition::map())) {
            return;
        }

        TicketLink::create([
            'from_ticket_id' => $ticket->id,
            'to_ticket_id' => $previous->id,
            'type' => self::LINK_TYPE,
            'created_by' => $actorId,
        ]);
    }

    /**
     * A random active holder of the assign role, never the one who got the
     * last exception ticket.
     *
     * inRandomOrder() rather than shuffling in PHP: the pool is every back-end
     * developer, and there is no reason to load them all to keep one.
     *
     * The no-repeat exclusion is dropped when it would empty the pool — a team
     * with one available back-end developer gets every exception, which is
     * correct. Refusing to assign rather than repeating a person would mean the
     * ticket nobody owns, which is worse than the ticket the same person owns
     * twice.
     *
     * The availability filters are NOT dropped that way. "Everybody is on
     * leave" has to fail loudly, because the alternative is quietly assigning
     * an error to somebody on holiday and docking them for it four hours later.
     * The failure reaches the sender's log, and the error is still recorded in
     * the Back Office regardless — nothing is lost except the automatic
     * assignment, which is precisely the part that had no correct answer.
     */
    private function pickAssignee(): User
    {
        $roleId = $this->assignRoleId();
        $last = $this->lastExceptionAssigneeId();
        $today = now()->toDateString();

        $pool = User::query()
            ->where('is_active', true)
            ->where('role_id', $roleId)
            // Anyone whose leave covers today is out of the draw. whereDoesntHave
            // rather than pluck-then-exclude so this stays one query however
            // much leave history the table holds.
            ->whereDoesntHave('leaves', fn ($q) => $q->overlapping($today, $today));

        $picked = (clone $pool)
            ->when($last !== null, fn ($q) => $q->whereKeyNot($last))
            ->inRandomOrder()
            ->first();

        $picked ??= (clone $pool)->inRandomOrder()->first();

        if ($picked === null) {
            throw new RuntimeException(
                'مفيش مبرمج باك إند متاح دلوقتي يتوزّع عليه الاكسبشن — '
                . 'كلهم موقوفين أو في أجازة النهاردة.'
            );
        }

        return $picked;
    }

    /**
     * Who is holding the most recent exception ticket.
     *
     * Read off the subtask rather than the ticket, because the subtask is where
     * the assignee actually lives — the ticket has no single owner column, and
     * the role assignment is the same fact one join further away.
     */
    private function lastExceptionAssigneeId(): ?int
    {
        return TicketSubtask::query()
            ->whereNotNull('assignee_id')
            ->where('role_id', $this->assignRoleId())
            ->whereHas('ticket', fn ($q) => $q
                ->whereNotNull('exception_fingerprint'))
            ->orderByDesc('id')
            ->value('assignee_id');
    }

    private function assignRoleId(): int
    {
        $key = (string) config('tickets.intake.assign_role', 'backend');
        $id = Role::idByKey($key);

        if ($id === null) {
            throw new RuntimeException("الدور «{$key}» مش موجود، فمفيش حد يتوزّع عليه الاكسبشن.");
        }

        return $id;
    }

    /**
     * The one line a developer reads in a list of forty tickets.
     *
     * The server comes first and in brackets because errors arrive from several
     * of them, and "which server" is the question that decides whether this is
     * even yours to look at. Without it, four servers throwing the same message
     * produce four tickets nobody can tell apart in a list.
     *
     * Newlines are flattened: an exception message can be multi-line, and a
     * ticket title that wraps to six rows breaks every list it appears in.
     */
    private function title(array $data): string
    {
        $server = $this->serverName($data) ?: 'سيرفر غير معروف';
        $message = trim(preg_replace('/\s+/u', ' ', (string) $data['message']) ?? '');

        if ($message === '') {
            $message = 'اكسبشن من غير رسالة';
        }

        $head = "[{$server}] ";
        $room = 255 - mb_strlen($head);

        return $head . (mb_strlen($message) > $room
            ? mb_substr($message, 0, $room - 1) . '…'
            : $message);
    }

    /**
     * Everything needed to work the error without opening another system.
     *
     * The full trace is here, not a link to it. A developer who has to leave
     * the ticket to see the stack is a developer who will not look at the
     * stack, and the whole reason this ticket exists is to be the place the
     * work happens.
     *
     * Every value is escaped with e() before it goes near the HTML, and the
     * whole thing goes through Purifier on the way out — the same treatment the
     * editor's own output gets (CLAUDE.md § 5). None of this text was written
     * by anybody we trust: it is an exception message, a URL and a request
     * payload, all of which can contain whatever a caller sent.
     */
    private function description(array $data, ?Ticket $previous): string
    {
        $rows = array_filter([
            'السيرفر' => $this->serverName($data),
            'الرابط' => $data['url'] ?? null,
            'الميثود' => $data['method'] ?? null,
            'واجهة العميل' => $data['front_end_model'] ?? null,
            'المؤسسة' => $data['organization_id'] ?? null,
            'عنوان IP' => $data['ip_address'] ?? null,
            'عدد المرات' => $data['occurrences'] ?? null,
            'وقت الحدوث' => $data['occurred_at'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $html = '<p><strong>' . e((string) $data['message']) . '</strong></p>';

        $html .= '<ul>';
        foreach ($rows as $label => $value) {
            $html .= '<li>' . e($label) . ': ' . e((string) $value) . '</li>';
        }
        $html .= '</ul>';

        if ($previous !== null) {
            $html .= '<p>الاكسبشن ده رجع تاني بعد ما التذكرة <strong>'
                . e($previous->ticket_number) . '</strong> اتقفلت.</p>';
        }

        if (! empty($data['alert_url'])) {
            $url = (string) $data['alert_url'];
            $html .= '<p><a href="' . e($url) . '" target="_blank" rel="noopener noreferrer">'
                . 'التفاصيل الكاملة في الباك أوفيس</a></p>';
        }

        $html .= $this->block('التتبع (Stack trace)', $data['trace'] ?? null);
        $html .= $this->block('البيانات المرسلة', $data['payload'] ?? null);
        $html .= $this->block('المستخدم', $data['user_details'] ?? null);

        return Purifier::clean($html);
    }

    /** A titled <pre> block, or nothing at all when the value is empty. */
    private function block(string $title, mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        return '<p><strong>' . e($title) . '</strong></p><pre>' . e($value) . '</pre>';
    }

    private function serverName(array $data): string
    {
        return trim((string) ($data['server_name'] ?? ''));
    }

    private function truncate(mixed $value, int $max): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
