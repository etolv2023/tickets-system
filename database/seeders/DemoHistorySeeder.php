<?php

namespace Database\Seeders;

use App\Casts\PriorityValue;
use App\Casts\TicketStatusValue;
use App\Enums\SubtaskSide;
use App\Enums\SubtaskStatus;
use App\Enums\TicketType;
use App\Enums\WorkSide;
use App\Models\Company;
use App\Models\Rating;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketWorkLog;
use App\Models\User;
use App\Notifications\NotificationEvent;
use App\Services\NotificationService;
use App\Services\PointEngineService;
use App\Services\SlaService;
use App\Services\SubtaskService;
use App\Services\TicketNumberService;
use App\Services\TimeTrackingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Five months of traffic — the seeder you open the reports with.
 *
 * The other demo seeders make individual screens non-empty. This one exists so
 * the screens that only mean anything over TIME have something to show: the
 * points report needs several closed periods to compare, the team report needs
 * a spread of people, the notification inbox needs a history with read and
 * unread in it, and /my-timesheet needs hours in the week you are actually
 * looking at.
 *
 * Every ticket here is run through the real services — SubtaskService for the
 * points default, TimeTrackingService for the rollups, PointEngineService for
 * the award, NotificationService for the recipients. Nothing is inserted behind
 * their backs, so what you see on screen is what the application itself would
 * have produced. That is the whole point: if a number here is wrong, the
 * feature is wrong.
 *
 * The full earning cycle, visible end to end on any closed ticket:
 *
 *   subtask created  → points prefilled with the flat default (SubtaskService)
 *   subtask done     → it now has an earner and a value
 *   ticket resolved  → PointEngineService pays every Done subtask, once, and
 *                      stamps points_awarded_at so a reopen can never pay twice
 *   period           → taken from resolved_at, so March's work is March's bonus
 *
 * Deterministic: the same seed every run, so a screenshot taken today still
 * matches the data tomorrow.
 */
class DemoHistorySeeder extends Seeder
{
    private const SEED = 20260718;

    /** How far back the history runs, and how many tickets land in each month. */
    private const MONTHS = [4 => 9, 3 => 10, 2 => 9, 1 => 9, 0 => 7];

    private TicketNumberService $numbers;

    private SlaService $sla;

    private SubtaskService $subtasks;

    private TimeTrackingService $time;

    private PointEngineService $points;

    private NotificationService $notify;

    /** @var array<string, User> */
    private array $people = [];

    /** @var array<int, Company> */
    private array $companies = [];

    private int $cursor = 0;

    public function run(): void
    {
        mt_srand(self::SEED);

        $this->numbers = app(TicketNumberService::class);
        $this->sla = app(SlaService::class);
        $this->subtasks = app(SubtaskService::class);
        $this->time = app(TimeTrackingService::class);
        $this->points = app(PointEngineService::class);
        $this->notify = app(NotificationService::class);

        $this->people = User::query()
            ->whereIn('email', [
                'admin@etolv.test', 'manager@etolv.test', 'support@etolv.test',
                'frontend@etolv.test', 'backend@etolv.test', 'fullstack@etolv.test',
                'tester@etolv.test',
            ])
            ->get()
            ->keyBy(fn (User $u) => explode('@', $u->email)[0])
            ->all();

        $this->companies = Company::with('contacts')->active()->get()->values()->all();

        if ($this->companies === [] || ! isset($this->people['support'])) {
            $this->command?->warn('  DemoHistorySeeder: مفيش شركات أو مستخدمين — اتخطّت.');

            return;
        }

        $made = 0;

        foreach (self::MONTHS as $back => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->makeTicket($back, $i, $count);
                $made++;
            }
        }

        $this->settleInbox();
        $this->report($made);
    }

    /**
     * One ticket, played forward from "reported" to wherever it got to.
     *
     * Months in the past are finished business: closed, paid, rated. The
     * current month is deliberately unfinished — a board with nothing in
     * progress teaches nothing.
     */
    private function makeTicket(int $monthsBack, int $index, int $count): void
    {
        $item = $this->nextItem();
        $company = $this->companies[$this->cursor % count($this->companies)];

        // Every third one comes from inside the team instead of a client: the
        // team lead's own technical debt, which earns points like anything
        // else. He also does the analysis on his own requests, so the lead's
        // timesheet and points ledger are not permanently empty.
        $internal = $this->cursor % 3 === 2;

        $reportedAt = $this->reportedAt($monthsBack, $index, $count);
        $priority = PriorityValue::for($item['priority']);
        $type = TicketType::from($item['type']);

        $ticket = Ticket::create([
            'ticket_number' => $this->numbers->next((int) $reportedAt->format('Y')),
            'company_id' => $internal ? null : $company->id,
            'contact_id' => $internal ? null : $company->contacts->first()?->id,
            'requested_by' => $internal ? $this->people['admin']->id : null,
            'reporter_name' => $internal
                ? $this->people['admin']->name
                : ($company->contacts->first()?->name ?? 'غير محدد'),
            'reporter_erp_id' => $internal ? null : $company->contacts->first()?->erp_employee_id,
            'title' => $item['title'],
            'description' => '<p>' . e($item['detail']) . '</p>',
            'type' => $type,
            'priority' => $priority,
            'status' => TicketStatusValue::for('new'),
            'module' => $item['module'],
            'created_by' => $this->people['support']->id,
            'approval_status' => $type->needsApproval() ? 'pending' : 'not_required',
            'reported_at' => $reportedAt,
            'first_response_at' => $reportedAt->addHours(mt_rand(1, 5)),
            'sla_due_at' => $this->sla->dueAt($priority, $reportedAt),
        ]);

        $this->at($reportedAt, fn () => $this->notify->dispatch(
            $ticket,
            NotificationEvent::TicketCreated,
            ($internal ? 'طلب داخلي جديد' : 'تذكرة جديدة من ' . $company->name) . " — {$ticket->title}",
            $this->people['support']->id,
        ));

        $finished = $monthsBack > 0;
        $stage = $finished ? 'closed' : $this->openStage($index);

        if ($type->needsApproval()) {
            $this->approve($ticket, $reportedAt, $stage);
        }

        // pending_approval stops here on purpose: nobody is assigned, nothing is
        // planned, and the approvals queue needs rows that look like that.
        if ($ticket->approval_status === 'pending') {
            $this->history($ticket, 'new', 'pending_approval', $reportedAt->addHours(2));
            $ticket->forceFill(['status' => TicketStatusValue::for('pending_approval')])->save();

            return;
        }

        // A demo-only planning hint (frontend/backend/both/inquiry) that decides
        // who gets seeded onto the ticket. Not a ticket column any more — just
        // shapes the fake crew so the reports show a realistic mix.
        $crew = $this->assign($ticket, $item['scope'], $reportedAt, $internal);
        $plan = $this->planSubtasks($ticket, $item, $crew, $reportedAt, $stage);

        $this->logWork($ticket, $stage, $reportedAt);
        $this->talk($ticket, $reportedAt, $internal);
        $this->settle($ticket, $stage, $reportedAt, $plan);
    }

    /** F15: the admin decides, and everyone waiting on it hears about it. */
    private function approve(Ticket $ticket, CarbonImmutable $reportedAt, string $stage): void
    {
        // One of the current-month features is left waiting, so the approvals
        // queue is not permanently empty.
        if ($stage === 'awaiting') {
            return;
        }

        $at = $reportedAt->addDays(1)->setTime(10, 30);

        $ticket->forceFill([
            'approval_status' => 'approved',
            'approved_by' => $this->people['admin']->id,
            'approved_at' => $at,
        ])->save();

        $this->at($at, fn () => $this->notify->dispatch(
            $ticket,
            NotificationEvent::TicketApproved,
            "اتوافق على {$ticket->ticket_number} — تقدروا تبدأوا",
            $this->people['admin']->id,
        ));
    }

    /**
     * Who is on it. The fullstack developer takes the frontend seat on some
     * tickets so the reports have more than one name per side.
     *
     * @return array<string, User>
     */
    private function assign(Ticket $ticket, string $scopeHint, CarbonImmutable $reportedAt, bool $internal): array
    {
        $frontend = $this->cursor % 3 === 0 ? $this->people['fullstack'] : $this->people['frontend'];

        // On an internal request the person who raised it does the analysis
        // himself rather than handing it to support — so the team lead has
        // hours on his own timesheet and rows in his own points ledger, which
        // is the whole reason internal tickets earn like any other. F19
        $crew = [
            'support' => $internal ? $this->people['admin'] : $this->people['support'],
            'qa' => $this->people['tester'],
        ];

        if (in_array($scopeHint, ['frontend', 'both'], true)) {
            $crew['frontend'] = $frontend;
        }

        if (in_array($scopeHint, ['backend', 'both'], true)) {
            $crew['backend'] = $this->people['backend'];
        }

        $at = $reportedAt->addHours(6);

        $ticket->forceFill([
            'assigned_frontend_id' => $crew['frontend']->id ?? null,
            'assigned_backend_id' => $crew['backend']->id ?? null,
            'tester_id' => $scopeHint === 'inquiry' ? null : $crew['qa']->id,
            'status' => TicketStatusValue::for('assigned'),
        ])->save();

        $this->history($ticket, 'new', 'assigned', $at);

        $this->at($at, fn () => $this->notify->dispatch(
            $ticket,
            NotificationEvent::TicketAssigned,
            "اتوزعت عليك {$ticket->ticket_number} — {$ticket->title}",
            $this->people['support']->id,
        ));

        return $crew;
    }

    /**
     * The subtasks, and the hours actually burned on them.
     *
     * Points are never passed in: SubtaskService fills them with the flat
     * default, which is exactly what the create form does. That is what makes
     * the points report on this data trustworthy rather than decorative.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, User>  $crew
     * @return array<int, array<string, mixed>>
     */
    private function planSubtasks(
        Ticket $ticket,
        array $item,
        array $crew,
        CarbonImmutable $reportedAt,
        string $stage,
    ): array {
        $rows = [];
        $day = 0;

        foreach ($item['work'] as [$sideKey, $title, $hours]) {
            $side = SubtaskSide::from($sideKey);
            $owner = $crew[$sideKey] ?? null;

            // A side nobody is on has no work on it — a backend-only ticket
            // must not sprout a frontend subtask assigned to nobody.
            if ($owner === null) {
                continue;
            }

            $status = $this->subtaskStatus($stage, $sideKey);

            $dueAt = $reportedAt->addDays(1 + $day);
            $day += mt_rand(1, 2);

            // Work still to do belongs in the future — that is what fills the
            // calendar. Work already DONE does not: a subtask finished next
            // Tuesday, with hours logged against next Tuesday, is nonsense.
            if ($status === SubtaskStatus::Done && $dueAt->isFuture()) {
                $dueAt = CarbonImmutable::today();
            }

            $due = $dueAt->toDateString();

            $subtask = $this->subtasks->create($ticket, [
                'title' => $title,
                'side' => $side,
                'status' => $status,
                'assignee_id' => $owner->id,
                'due_date' => $due,
                'estimated_hours' => $hours,
                'started_at' => $status === SubtaskStatus::Todo ? null : $reportedAt->addDays($day),
                'completed_at' => $status === SubtaskStatus::Done ? CarbonImmutable::parse($due)->setTime(16, 0) : null,
                'blocked_reason' => $status === SubtaskStatus::Blocked ? 'مستني رد العميل على البيانات الناقصة' : null,
            ], $this->people['support']->id);

            // Hours land on the day the work finished, give or take — which is
            // what puts figures in the week /my-timesheet is showing.
            if ($status !== SubtaskStatus::Todo) {
                $spent = $status === SubtaskStatus::Done
                    ? round($hours * mt_rand(80, 135) / 100, 1)
                    : round($hours * mt_rand(25, 60) / 100, 1);

                if ($spent > 0) {
                    $this->time->log($ticket, [
                        'subtask_id' => $subtask->id,
                        'hours' => $spent,
                        'spent_on' => $due,
                        // A note that repeats the subtask title tells the
                        // reader nothing the row does not already say.
                        'note' => $this->note($status, $spent, $hours),
                    ], $owner->id);
                }
            }

            if ($status === SubtaskStatus::Done) {
                $rows[] = ['user' => $owner, 'side' => $side, 'points' => (float) $subtask->points];
            }

            if ($status === SubtaskStatus::Blocked) {
                $this->at($reportedAt->addDays($day), fn () => $this->notify->dispatch(
                    $ticket,
                    NotificationEvent::SubtaskBlocked,
                    "«{$title}» اتقفلت — مستنيين رد العميل",
                    $owner->id,
                    subtask: $subtask,
                ));
            }
        }

        return $rows;
    }

    /** What someone would actually type in the note box — including nothing. */
    private function note(SubtaskStatus $status, float $spent, float $estimate): ?string
    {
        if ($status !== SubtaskStatus::Done) {
            return 'شغل جاري';
        }

        if ($spent > $estimate * 1.15) {
            return 'خد وقت أطول من المتوقع';
        }

        return $this->cursor % 2 === 0 ? 'خلص وتسلّم' : null;
    }

    /** Work logs have to agree with the status, or the board offers a "بدأت" button on finished work. F07 */
    private function logWork(Ticket $ticket, string $stage, CarbonImmutable $reportedAt): void
    {
        $logStatus = match ($stage) {
            'assigned', 'awaiting' => 'pending',
            'working' => 'in_progress',
            default => 'done',
        };

        foreach (WorkSide::cases() as $side) {
            $userId = $ticket->{$side->assigneeColumn()};

            if ($userId === null) {
                continue;
            }

            TicketWorkLog::create([
                'ticket_id' => $ticket->id,
                'side' => $side->value,
                'user_id' => $userId,
                'status' => $logStatus,
                'started_at' => $logStatus === 'pending' ? null : $reportedAt->addHours(8),
                'finished_at' => $logStatus === 'done' ? $reportedAt->addDays(3) : null,
                'duration_minutes' => $logStatus === 'done' ? mt_rand(6, 40) * 60 : null,
            ]);
        }
    }

    /**
     * Resolve, pay, notify, rate, close — in that order, because that is the
     * order the application does it in.
     *
     * @param  array<int, array<string, mixed>>  $earners
     */
    private function settle(Ticket $ticket, string $stage, CarbonImmutable $reportedAt, array $earners): void
    {
        if (! in_array($stage, ['resolved', 'closed'], true)) {
            $this->parkOpen($ticket, $stage, $reportedAt);

            return;
        }

        $resolvedAt = $reportedAt->addDays(mt_rand(3, 11))->setTime(mt_rand(11, 17), 0);

        $ticket->forceFill([
            'status' => TicketStatusValue::for('resolved'),
            'resolved_at' => $resolvedAt,
            'resolution_note' => 'اتحلت واتراجعت مع العميل.',
        ])->save();

        $this->history($ticket, 'in_progress', 'resolved', $resolvedAt);

        $this->at($resolvedAt, fn () => $this->notify->dispatch(
            $ticket,
            NotificationEvent::TicketResolved,
            "{$ticket->ticket_number} اتحلت — {$ticket->title}",
            $this->people['backend']->id,
        ));

        // The award reads resolved_at for the period, so it must already be
        // saved — this line is why the ordering above is not cosmetic.
        $this->points->award($ticket->fresh());

        if ($earners !== []) {
            $total = array_sum(array_column($earners, 'points'));

            $this->at($resolvedAt->addMinutes(1), fn () => $this->notify->dispatch(
                $ticket,
                NotificationEvent::PointsAwarded,
                "اتصرفت {$total} نقطة على {$ticket->ticket_number} حسب الصبتاسكات المخلصة",
                null,
            ));
        }

        if ($stage !== 'closed') {
            return;
        }

        $closedAt = $resolvedAt->addDays(mt_rand(1, 4));

        $ticket->forceFill([
            'status' => TicketStatusValue::for('closed'),
            'closed_at' => $closedAt,
            'client_notified_at' => $resolvedAt->addHours(3),
            'client_notified_by' => $this->people['support']->id,
        ])->save();

        $this->history($ticket, 'resolved', 'closed', $closedAt);
        $this->rate($ticket, $earners, $closedAt);
    }

    /** An unfinished ticket still has to sit in a status that matches its work logs. */
    private function parkOpen(Ticket $ticket, string $stage, CarbonImmutable $reportedAt): void
    {
        $to = match ($stage) {
            'working' => 'in_progress',
            'testing' => 'testing',
            default => 'assigned',
        };

        if ($to === 'assigned') {
            return;
        }

        $at = $reportedAt->addDays(2);

        $ticket->forceFill(['status' => TicketStatusValue::for($to)])->save();
        $this->history($ticket, 'assigned', $to, $at);

        $this->at($at, fn () => $this->notify->dispatch(
            $ticket,
            NotificationEvent::TicketStatusChanged,
            "{$ticket->ticket_number} بقت " . TicketStatusValue::for($to)->label(),
            $this->people['support']->id,
        ));
    }

    /** F17: optional, so only some tickets carry one. @param array<int, array<string, mixed>> $earners */
    private function rate(Ticket $ticket, array $earners, CarbonImmutable $at): void
    {
        if ($earners === [] || $this->cursor % 3 === 1) {
            return;
        }

        foreach ($earners as $earner) {
            $side = $earner['side']->toPointSide();

            if ($side === null) {
                continue;
            }

            Rating::firstOrCreate(
                ['ticket_id' => $ticket->id, 'ratee_id' => $earner['user']->id, 'side' => $side->value],
                [
                    'score' => mt_rand(7, 10),
                    'comment' => 'تسليم في الوقت.',
                    'rated_by' => $this->people['manager']->id,
                    'rated_at' => $at,
                ]
            );
        }

        $this->at($at, fn () => $this->notify->dispatch(
            $ticket,
            NotificationEvent::RatingGiven,
            "تقييم جديد على {$ticket->ticket_number}",
            $this->people['manager']->id,
        ));
    }

    /** A couple of comments so the ticket reads like a conversation, not a form. */
    private function talk(Ticket $ticket, CarbonImmutable $reportedAt, bool $internal): void
    {
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->people['support']->id,
            'body' => $internal
                ? '<p>اتكلمت مع المدير التقني، الأولوية دي متفق عليها.</p>'
                : '<p>اتواصلنا مع العميل واتأكدنا من تفاصيل المشكلة.</p>',
            'is_internal' => false,
            'created_at' => $reportedAt->addHours(4),
        ]);

        if ($this->cursor % 2 !== 0) {
            return;
        }

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $this->people['backend']->id,
            'body' => '<p>لقيت السبب في الاستعلام اللي بيتنادى جوه اللوب. بصلحه.</p>',
            'is_internal' => true,
            'created_at' => $reportedAt->addDays(1)->addHours(2),
        ]);

        $this->at($reportedAt->addHours(4), fn () => $this->notify->dispatch(
            $ticket,
            NotificationEvent::CommentAdded,
            "تعليق جديد على {$ticket->ticket_number}",
            $this->people['support']->id,
        ));
    }

    /**
     * The inbox after five months: old things read, this week's things not.
     * An inbox where everything is unread is as useless as one where nothing is.
     */
    private function settleInbox(): void
    {
        DB::table('notifications')
            ->whereNull('read_at')
            ->where('created_at', '<', now()->subDays(6))
            ->update(['read_at' => DB::raw('DATE_ADD(created_at, INTERVAL 1 DAY)')]);
    }

    private function history(Ticket $ticket, string $from, string $to, CarbonImmutable $at): void
    {
        $ticket->statusHistory()->create([
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $this->people['support']->id,
            'created_at' => $at,
        ]);
    }

    /**
     * Runs a callback with the clock moved, so a notification created now
     * carries the timestamp of the thing it is about. Without this the whole
     * five months of history arrives in the inbox stamped "منذ ثانية".
     */
    private function at(CarbonImmutable $moment, callable $callback): void
    {
        // Never ahead of the clock. A notification stamped "3 أيام من الآن"
        // announces something that has not happened, which is worse than no
        // notification at all — and the newest tickets' events would land there
        // as soon as an event was a couple of days after its ticket.
        Carbon::setTestNow($moment->isFuture() ? CarbonImmutable::now() : $moment);

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }

    /** Where an unfinished ticket got to. Spread so the board has all four columns. */
    private function openStage(int $index): string
    {
        return match ($index % 5) {
            0 => 'working',
            1 => 'testing',
            2 => 'resolved',
            3 => 'awaiting',
            default => 'assigned',
        };
    }

    private function subtaskStatus(string $stage, string $side): SubtaskStatus
    {
        if (in_array($stage, ['resolved', 'closed'], true)) {
            return SubtaskStatus::Done;
        }

        return match ($stage) {
            'testing' => $side === 'qa' ? SubtaskStatus::InProgress : SubtaskStatus::Done,
            'working' => match ($side) {
                'support' => SubtaskStatus::Done,
                'backend' => SubtaskStatus::InProgress,
                'qa' => SubtaskStatus::Todo,
                default => $this->cursor % 4 === 0 ? SubtaskStatus::Blocked : SubtaskStatus::Todo,
            },
            default => SubtaskStatus::Todo,
        };
    }

    /**
     * Spread inside the month, and — for the current month — spread around
     * TODAY rather than from the first, so this week's calendar and timesheet
     * are populated whatever day you happen to run this.
     */
    private function reportedAt(int $monthsBack, int $index, int $count): CarbonImmutable
    {
        if ($monthsBack === 0) {
            // Backwards from today only. A ticket cannot have been reported
            // tomorrow — planned WORK lives in the future (subtask due dates),
            // the report date never does.
            $days = ($count - 1 - $index) * 2;

            return CarbonImmutable::today()->subDays($days)->setTime(mt_rand(9, 16), mt_rand(0, 59));
        }

        $month = CarbonImmutable::today()->subMonths($monthsBack)->startOfMonth();
        $day = min(27, 1 + (int) floor($index * 27 / max(1, $count)));

        return $month->addDays($day - 1)->setTime(mt_rand(9, 16), mt_rand(0, 59));
    }

    /** @return array<string, mixed> */
    private function nextItem(): array
    {
        $catalog = $this->catalog();

        return $catalog[$this->cursor++ % count($catalog)];
    }

    /**
     * Believable work, with believable pieces. Each entry's work list is
     * [side, title, estimated hours]; a side with nobody on the ticket is
     * skipped, so one entry produces a different plan on a backend-only ticket
     * than on a both-sides one.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            ['type' => 'bug', 'scope' => 'backend', 'priority' => 'urgent', 'module' => 'المبيعات',
             'title' => 'الفاتورة بتحسب الخصم بعد الضريبة مش قبلها',
             'detail' => 'كل فواتير الخصم بتطلع بقيمة ضريبة أعلى من الصح، والفرق بيظهر في الإقرار الشهري.',
             'work' => [['support', 'تجميع أمثلة الفواتير الغلط من العميل', 2],
                        ['backend', 'تصحيح ترتيب حساب الخصم والضريبة', 6],
                        ['qa', 'مراجعة ١٢ فاتورة قديمة بعد التصحيح', 3]]],

            ['type' => 'bug', 'scope' => 'frontend', 'priority' => 'high', 'module' => 'المخزون',
             'title' => 'شاشة الجرد بتفضل تحمّل من غير ما تعرض حاجة',
             'detail' => 'بيحصل مع الفروع اللي عندها أصناف أكتر من ٥٠٠٠، والشاشة بتفضل على اللودر.',
             'work' => [['support', 'تحديد الفروع اللي المشكلة بتحصل فيها', 1.5],
                        ['frontend', 'ترقيم الصفحات بدل تحميل الأصناف كلها', 7],
                        ['qa', 'اختبار على فرع فيه ٨٠٠٠ صنف', 2.5]]],

            ['type' => 'feature', 'scope' => 'both', 'priority' => 'medium', 'module' => 'التقارير',
             'title' => 'كشف حساب العميل بصيغة PDF',
             'detail' => 'العميل عايز يبعت الكشف بالإيميل من غير ما يصدّره Excel ويحوله بإيده.',
             'work' => [['support', 'تجميع شكل الكشف المطلوب من العميل', 2],
                        ['backend', 'بناء الكشف وتجميع الحركات', 9],
                        ['frontend', 'شاشة المعاينة وزرار التصدير', 6],
                        ['qa', 'مطابقة الـ PDF مع كشف الإكسل', 3]]],

            ['type' => 'inquiry', 'scope' => 'inquiry', 'priority' => 'low', 'module' => null,
             'title' => 'إزاي أطلع تقرير الأرصدة لفرع واحد بس؟',
             'detail' => 'المحاسب محتاج يفلتر التقرير على فرع واحد.',
             'work' => [['support', 'شرح خطوات الفلترة وإرسال صور', 1]]],

            ['type' => 'bug', 'scope' => 'both', 'priority' => 'urgent', 'module' => 'الحسابات',
             'title' => 'الرصيد الافتتاحي بيتصفّر بعد ترحيل السنة',
             'detail' => 'بعد الترحيل السنوي بتظهر أرصدة أول المدة أصفار لكل الموردين.',
             'work' => [['support', 'أخذ نسخة من قاعدة العميل قبل الترحيل', 2],
                        ['backend', 'تصحيح ترحيل أرصدة أول المدة', 10],
                        ['frontend', 'رسالة تأكيد قبل تنفيذ الترحيل', 4],
                        ['qa', 'ترحيل تجريبي على نسخة العميل', 4]]],

            ['type' => 'feature', 'scope' => 'frontend', 'priority' => 'low', 'module' => 'الإعدادات',
             'title' => 'تغيير لوجو الشركة في رأس التقارير',
             'detail' => 'كل تقرير بيتطبع بلوجو قديم، وعايزين اللوجو يتغير من الإعدادات.',
             'work' => [['support', 'استلام اللوجو بالمقاسات المطلوبة', 1],
                        ['frontend', 'رفع اللوجو من الإعدادات وعرضه في الرأس', 5],
                        ['qa', 'طباعة ٤ تقارير للتأكد من المقاس', 2]]],

            ['type' => 'new_module', 'scope' => 'both', 'priority' => 'medium', 'module' => 'المشاريع',
             'title' => 'موديول متابعة المشاريع والمراحل',
             'detail' => 'متابعة المشروع بمراحله ونسب الإنجاز والمصروفات لكل مرحلة.',
             'work' => [['support', 'جلسة تحليل مع العميل وكتابة المتطلبات', 4],
                        ['backend', 'جداول المشاريع والمراحل والمصروفات', 14],
                        ['frontend', 'شاشات المشروع ولوحة المراحل', 12],
                        ['qa', 'جولة اختبار كاملة للموديول', 6]]],

            ['type' => 'bug', 'scope' => 'backend', 'priority' => 'high', 'module' => 'المشتريات',
             'title' => 'أمر الشراء بيتكرر لو اتضغط حفظ مرتين',
             'detail' => 'الضغط السريع على حفظ بيسجل نفس أمر الشراء مرتين برقمين مختلفين.',
             'work' => [['support', 'حصر الأوامر المكررة عند العميل', 2],
                        ['backend', 'منع التكرار على مستوى الحفظ', 5],
                        ['qa', 'محاولة تكرار الحفظ بعد التصليح', 2]]],

            ['type' => 'feature', 'scope' => 'backend', 'priority' => 'medium', 'module' => 'الموارد البشرية',
             'title' => 'ربط البصمة بحساب الحضور والانصراف',
             'detail' => 'استيراد ملف البصمة يومياً وحساب التأخير والإضافي أوتوماتيك.',
             'work' => [['support', 'أخذ عينة من ملف جهاز البصمة', 2],
                        ['backend', 'قارئ الملف وحساب التأخير والإضافي', 11],
                        ['qa', 'مطابقة نتيجة شهر كامل يدوي', 4]]],

            ['type' => 'bug', 'scope' => 'frontend', 'priority' => 'medium', 'module' => 'نقاط البيع',
             'title' => 'زرار الدفع مش ظاهر على شاشة التابلت',
             'detail' => 'على المقاسات الصغيرة الزرار بيخرج برة الشاشة والكاشير مش لاقيه.',
             'work' => [['support', 'تحديد مقاسات الأجهزة عند العميل', 1],
                        ['frontend', 'ضبط تجاوب شاشة الدفع', 4],
                        ['qa', 'اختبار على ٣ مقاسات', 2]]],

            ['type' => 'feature', 'scope' => 'both', 'priority' => 'high', 'module' => 'المخزون',
             'title' => 'تنبيه تلقائي عند وصول الصنف لحد الطلب',
             'detail' => 'إشعار للمسؤول لما رصيد الصنف يوصل للحد الأدنى المحدد.',
             'work' => [['support', 'تحديد حدود الطلب مع أمين المخزن', 2],
                        ['backend', 'مهمة يومية تفحص الأرصدة وتبعت التنبيه', 8],
                        ['frontend', 'شاشة إعداد حدود الطلب', 5],
                        ['qa', 'اختبار التنبيه على أصناف تجريبية', 3]]],

            ['type' => 'inquiry', 'scope' => 'inquiry', 'priority' => 'low', 'module' => null,
             'title' => 'الفرق بين تقرير الأرباح وتقرير المبيعات إيه؟',
             'detail' => 'المحاسب لقى فرق بين التقريرين وعايز يفهم سبب الاختلاف.',
             'work' => [['support', 'شرح أساس كل تقرير بمثال رقمي', 1.5]]],
        ];
    }

    private function report(int $made): void
    {
        $rows = DB::table('point_transactions')
            // Aliased `paid`, not `rows`: ROWS is reserved in MySQL 8.
            ->selectRaw('period, SUM(points) total, COUNT(*) paid')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $this->command?->info("  تاريخ كامل: {$made} تذكرة على " . count(self::MONTHS) . ' شهور.');

        foreach ($rows as $row) {
            $this->command?->info("    {$row->period}: {$row->paid} معاملة نقاط، إجمالي {$row->total}");
        }

        $unread = DB::table('notifications')->whereNull('read_at')->count();
        $all = DB::table('notifications')->count();
        $hours = DB::table('time_entries')->sum('hours');

        $this->command?->info("    إشعارات: {$all} منها {$unread} غير مقروء · ساعات مسجلة: {$hours}");
    }
}
