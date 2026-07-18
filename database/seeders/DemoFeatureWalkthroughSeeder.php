<?php

namespace Database\Seeders;

use App\Enums\SubtaskSide;
use App\Enums\SubtaskStatus;
use App\Enums\TicketScope;
use App\Enums\TicketType;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\PointEngineService;
use App\Services\SubtaskService;
use App\Services\TimeTrackingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * One feature, followed all the way through — the worked example.
 *
 * Every other demo seeder scatters a few rows to make screens non-empty. This
 * one builds a single believable piece of work so the relationships can be read
 * off the screen instead of guessed at:
 *
 *   - a FEATURE from a real client, which means it needed approval before
 *     anyone could be assigned (F15)
 *   - fourteen subtasks across frontend, backend and QA, spread over three
 *     weeks so the team calendar actually has a shape
 *   - every stage represented at once: done, in progress, blocked, still to do
 *   - logged time on the finished work, including one subtask deliberately
 *     over its estimate so the amber over-run styling has something to show
 *
 * THE POINT OF IT, literally: points are awarded PER TICKET, never per subtask.
 * point_transactions is UNIQUE(ticket_id, user_id, side) and has no subtask
 * column at all. A subtask is planning — it feeds the calendar, the estimate
 * and the progress bar. The ticket is the commitment to the client, and that
 * is the only thing that pays. Fourteen subtasks here produce exactly three
 * point rows: one frontend, one backend, one tester.
 */
class DemoFeatureWalkthroughSeeder extends Seeder
{
    public function run(): void
    {
        $subtasks = app(SubtaskService::class);
        $time = app(TimeTrackingService::class);

        $admin = User::where('email', 'admin@etolv.test')->firstOrFail();
        $support = User::where('email', 'support@etolv.test')->firstOrFail();
        $frontend = User::where('email', 'frontend@etolv.test')->firstOrFail();
        $backend = User::where('email', 'backend@etolv.test')->firstOrFail();
        $tester = User::where('email', 'tester@etolv.test')->firstOrFail();
        $company = Company::query()->firstOrFail();

        // Anchored to Monday of the current week so the calendar always shows
        // this feature in the month you happen to be looking at.
        $monday = Carbon::today()->startOfWeek(Carbon::SATURDAY);

        $ticket = Ticket::create([
            'ticket_number' => $this->nextNumber(),
            'company_id' => $company->id,
            'contact_id' => $company->contacts()->value('id'),
            'reporter_name' => 'م. ياسر عبد الحميد',
            'title' => 'موديول نقاط البيع — ربط الكاشير بالمخزون لحظياً',
            'description' => '<p>عايزين شاشة كاشير تخصم من المخزون لحظة البيع، '
                . 'وتطبع فاتورة ضريبية، وتتعامل مع المرتجعات.</p>'
                . '<p>الفروع ٤ وكلها لازم تشوف نفس الرصيد في نفس اللحظة.</p>',
            'type' => TicketType::Feature,
            'scope' => TicketScope::Both,
            'priority' => 'high',
            'status' => 'in_progress',
            'reported_at' => $monday->copy()->subDays(6)->setTime(10, 15),
            'sla_due_at' => $monday->copy()->addDays(18)->setTime(17, 0),
            'first_response_at' => $monday->copy()->subDays(6)->setTime(11, 40),
            'created_by' => $support->id,
            // F15: a feature is dead in the water until an admin approves it.
            'approval_status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => $monday->copy()->subDays(5)->setTime(9, 30),
            'assigned_frontend_id' => $frontend->id,
            'assigned_backend_id' => $backend->id,
            'tester_id' => $tester->id,
        ]);

        foreach ($this->plan($monday, $frontend, $backend, $tester) as $row) {
            $subtask = $subtasks->create($ticket, [
                'title' => $row['title'],
                'side' => $row['side'],
                'status' => $row['status'],
                'assignee_id' => $row['assignee'],
                'start_date' => $row['start'],
                'due_date' => $row['due'],
                'estimated_hours' => $row['estimate'],
                'started_at' => $row['status'] === SubtaskStatus::Todo ? null : $row['start'],
                // Work still to do belongs in the future; work already finished
                // does not. The plan is anchored to this week's Saturday, so on
                // the first days of the week a "done" row's due date lands ahead
                // of the clock and the ticket claims it was finished next
                // Tuesday.
                'completed_at' => $row['status'] === SubtaskStatus::Done ? $this->past($row['due']) : null,
                'blocked_reason' => $row['blocked'] ?? null,
            ], $admin->id);

            // Time is logged only against work that actually happened. The
            // 'spent' figure is deliberately over the estimate on one row.
            if (($row['spent'] ?? 0) > 0) {
                $time->log($ticket, [
                    'subtask_id' => $subtask->id,
                    'hours' => $row['spent'],
                    // Same reason: nobody logs hours for a day that hasn't come.
                    'spent_on' => $this->past($row['due']),
                    'note' => 'شغل ' . $row['title'],
                ], $row['assignee']);
            }
        }

        $this->conversation($ticket, $support, $backend, $monday);

        $this->command?->info('  المثال الكامل: ' . $ticket->ticket_number
            . ' — ' . $ticket->subtasks()->count() . ' صب تاسك، والنقاط لسه متصرفتش (التذكرة مش محلولة).');
    }

    /**
     * Fourteen subtasks over three weeks. Backend leads, frontend follows it,
     * QA closes each slice — which is what puts overlapping bars on the team
     * calendar instead of one flat row.
     *
     * @return array<int, array<string, mixed>>
     */
    private function plan(Carbon $monday, User $fe, User $be, User $qa): array
    {
        $d = fn (int $offset) => $monday->copy()->addDays($offset)->toDateString();

        return [
            // ---- Week one: done and paid for in hours ----
            ['title' => 'تحليل الفروق بين رصيد الكاشير ورصيد المخزون', 'side' => SubtaskSide::Backend,
             'status' => SubtaskStatus::Done, 'assignee' => $be->id,
             'start' => $d(-6), 'due' => $d(-4), 'estimate' => 6, 'spent' => 5.5],

            ['title' => 'تصميم سكيما جدول الحركات اللحظية', 'side' => SubtaskSide::Backend,
             'status' => SubtaskStatus::Done, 'assignee' => $be->id,
             'start' => $d(-4), 'due' => $d(-2), 'estimate' => 8, 'spent' => 9],

            ['title' => 'واير فريم شاشة الكاشير', 'side' => SubtaskSide::Frontend,
             'status' => SubtaskStatus::Done, 'assignee' => $fe->id,
             'start' => $d(-5), 'due' => $d(-3), 'estimate' => 5, 'spent' => 4],

            ['title' => 'مراجعة الواير فريم مع العميل', 'side' => SubtaskSide::Qa,
             'status' => SubtaskStatus::Done, 'assignee' => $qa->id,
             'start' => $d(-3), 'due' => $d(-2), 'estimate' => 2, 'spent' => 2],

            // ---- Week two: the live edge ----
            ['title' => 'API خصم المخزون لحظة البيع', 'side' => SubtaskSide::Backend,
             'status' => SubtaskStatus::Done, 'assignee' => $be->id,
             'start' => $d(0), 'due' => $d(2), 'estimate' => 10, 'spent' => 13.5],

            ['title' => 'قفل متزامن يمنع البيع المزدوج بين الفروع', 'side' => SubtaskSide::Backend,
             'status' => SubtaskStatus::InProgress, 'assignee' => $be->id,
             'start' => $d(2), 'due' => $d(5), 'estimate' => 12, 'spent' => 7],

            ['title' => 'شاشة الكاشير — الشبكة والبحث السريع', 'side' => SubtaskSide::Frontend,
             'status' => SubtaskStatus::Done, 'assignee' => $fe->id,
             'start' => $d(0), 'due' => $d(3), 'estimate' => 9, 'spent' => 8.5],

            ['title' => 'شاشة الكاشير — سلة البيع والدفع', 'side' => SubtaskSide::Frontend,
             'status' => SubtaskStatus::InProgress, 'assignee' => $fe->id,
             'start' => $d(3), 'due' => $d(6), 'estimate' => 11, 'spent' => 4],

            ['title' => 'اختبار سيناريو البيع المتزامن من فرعين', 'side' => SubtaskSide::Qa,
             'status' => SubtaskStatus::Blocked, 'assignee' => $qa->id,
             'start' => $d(5), 'due' => $d(7), 'estimate' => 4, 'spent' => 0,
             'blocked' => 'مستني القفل المتزامن يخلص الأول'],

            // ---- Week three: planned, nothing started ----
            ['title' => 'الفاتورة الضريبية — القالب والأرقام', 'side' => SubtaskSide::Backend,
             'status' => SubtaskStatus::Todo, 'assignee' => $be->id,
             'start' => $d(7), 'due' => $d(9), 'estimate' => 7],

            ['title' => 'طباعة الفاتورة على طابعة حرارية', 'side' => SubtaskSide::Frontend,
             'status' => SubtaskStatus::Todo, 'assignee' => $fe->id,
             'start' => $d(9), 'due' => $d(11), 'estimate' => 6],

            ['title' => 'المرتجعات — إرجاع الصنف للمخزون', 'side' => SubtaskSide::Backend,
             'status' => SubtaskStatus::Todo, 'assignee' => $be->id,
             'start' => $d(10), 'due' => $d(12), 'estimate' => 8],

            ['title' => 'شاشة المرتجعات', 'side' => SubtaskSide::Frontend,
             'status' => SubtaskStatus::Todo, 'assignee' => $fe->id,
             'start' => $d(12), 'due' => $d(14), 'estimate' => 6],

            ['title' => 'جولة اختبار كاملة قبل التسليم', 'side' => SubtaskSide::Qa,
             'status' => SubtaskStatus::Todo, 'assignee' => $qa->id,
             'start' => $d(14), 'due' => $d(16), 'estimate' => 6],
        ];
    }

    /**
     * A public exchange and one internal note, so both sides are visible.
     *
     * Timestamps are clamped: the client portal shows these, and a support
     * reply stamped "يومين من الآن" is a message from the future.
     */
    private function conversation(Ticket $ticket, User $support, User $backend, Carbon $monday): void
    {
        $when = fn (Carbon $moment) => $moment->isFuture() ? Carbon::now() : $moment;

        TicketComment::create([
            'ticket_id' => $ticket->id, 'user_id' => $support->id,
            'body' => '<p>اتكلمنا مع م. ياسر. اتفقنا الفروع الأربعة تشتغل على نفس الرصيد، '
                . 'والمرتجعات في مرحلة تانية بعد التسليم الأول.</p>',
            'is_internal' => false,
            'created_at' => $when($monday->copy()->subDays(5)->setTime(12, 0)),
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id, 'user_id' => $backend->id,
            'body' => '<p>القفل المتزامن أطول من التقدير — الفروع على نت ضعيف '
                . 'ومحتاجين retry. هزود يومين.</p>',
            'is_internal' => true,
            'created_at' => $when($monday->copy()->addDays(3)->setTime(15, 20)),
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id, 'user_id' => $support->id,
            'body' => '<p>شاشة الكاشير خلصت والبحث شغال. باقي السلة والدفع.</p>',
            'is_internal' => false,
            'created_at' => $when($monday->copy()->addDays(3)->setTime(16, 5)),
        ]);
    }

    /** A date that already happened — today at the latest. */
    private function past(string $date): string
    {
        $day = Carbon::parse($date);

        return ($day->isFuture() ? Carbon::today() : $day)->toDateString();
    }

    private function nextNumber(): string
    {
        $year = now()->year;
        $last = (int) Ticket::where('ticket_number', 'like', "TK-{$year}-%")
            ->selectRaw('MAX(CAST(SUBSTRING(ticket_number, 9) AS UNSIGNED)) AS n')
            ->value('n');

        return sprintf('TK-%d-%05d', $year, $last + 1);
    }
}
