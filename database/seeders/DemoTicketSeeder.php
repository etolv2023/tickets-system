<?php

namespace Database\Seeders;

use App\Casts\PriorityValue;
use App\Casts\TicketStatusValue;
use App\Enums\TicketScope;
use App\Enums\TicketType;
use App\Enums\WorkSide;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\TicketWorkLog;
use App\Models\User;
use App\Services\SlaService;
use App\Services\TicketNumberService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Development only. Tickets that look like real support traffic — including the
 * awkward ones: overdue, both-sides, a feature waiting on approval, an inquiry
 * answered on the spot (CLAUDE.md § 7.7).
 */
class DemoTicketSeeder extends Seeder
{
    public function run(): void
    {
        $numbers = app(TicketNumberService::class);
        $sla = app(SlaService::class);

        $support = User::where('email', 'support@etolv.test')->first();
        $frontend = User::where('email', 'frontend@etolv.test')->first();
        $backend = User::where('email', 'backend@etolv.test')->first();
        $fullstack = User::where('email', 'fullstack@etolv.test')->first();
        $tester = User::where('email', 'tester@etolv.test')->first();

        $companies = Company::with('contacts')->active()->get()->keyBy('code');

        // [days ago, company, title, type, scope, priority, status, front, back, tester]
        $rows = [
            [9, 'NILE', 'فاتورة المبيعات بتطلع بضريبة مضاعفة', TicketType::Bug, TicketScope::Backend, PriorityValue::for('urgent'), TicketStatusValue::for('in_progress'), null, 'backend', 'tester'],
            [7, 'DELTA', 'شاشة المخزون بتعلّق لما الأصناف تعدي 5000', TicketType::Bug, TicketScope::Both, PriorityValue::for('high'), TicketStatusValue::for('assigned'), 'frontend', 'backend', 'tester'],
            [6, 'DELTA', 'إزاي أطلع تقرير الأرصدة لفرع واحد؟', TicketType::Inquiry, TicketScope::Inquiry, PriorityValue::for('low'), TicketStatusValue::for('resolved'), null, null, null],
            [5, 'SHARQ', 'تصدير كشف حساب العميل PDF', TicketType::Feature, TicketScope::Both, PriorityValue::for('medium'), TicketStatusValue::for('pending_approval'), null, null, null],
            [4, 'NILE', 'زرار الحفظ مش ظاهر على الموبايل', TicketType::Bug, TicketScope::Frontend, PriorityValue::for('medium'), TicketStatusValue::for('dev_done'), 'fullstack', null, 'tester'],
            [3, 'KHIBRA', 'موديول متابعة المشاريع', TicketType::NewModule, TicketScope::Both, PriorityValue::for('low'), TicketStatusValue::for('pending_approval'), null, null, null],
            [2, 'DELTA', 'الرصيد الافتتاحي بيتصفر بعد الترحيل', TicketType::Bug, TicketScope::Backend, PriorityValue::for('urgent'), TicketStatusValue::for('new'), null, null, null],
            [1, 'SHARQ', 'تغيير لوجو الشركة في التقارير', TicketType::Feature, TicketScope::Frontend, PriorityValue::for('low'), TicketStatusValue::for('new'), null, null, null],
        ];

        $people = ['frontend' => $frontend, 'backend' => $backend, 'fullstack' => $fullstack, 'tester' => $tester];

        foreach ($rows as [$daysAgo, $code, $title, $type, $scope, $priority, $status, $front, $back, $test]) {
            $company = $companies[$code] ?? null;

            if ($company === null) {
                continue;
            }

            $contact = $company->contacts->first();
            $reportedAt = CarbonImmutable::now()->subDays($daysAgo)->subHours(random_int(0, 9));

            DB::transaction(function () use (
                $numbers, $sla, $company, $contact, $title, $type, $scope, $priority,
                $status, $reportedAt, $support, $people, $front, $back, $test
            ) {
                $resolved = in_array($status, [TicketStatusValue::for('resolved'), TicketStatusValue::for('closed')], true);

                $ticket = Ticket::updateOrCreate(
                    ['title' => $title, 'company_id' => $company->id],
                    [
                        'ticket_number' => $numbers->next((int) $reportedAt->format('Y')),
                        'contact_id' => $contact?->id,
                        'reporter_name' => $contact?->name ?? 'غير محدد',
                        'reporter_erp_id' => $contact?->erp_employee_id,
                        'description' => '<p>' . e($title) . '</p><p>المشكلة بتحصل مع كل المستخدمين وبتتكرر يومياً.</p>',
                        'type' => $type,
                        'scope' => $scope,
                        'priority' => $priority,
                        'status' => $status,
                        'created_by' => $support->id,
                        'assigned_frontend_id' => $front ? $people[$front]->id : null,
                        'assigned_backend_id' => $back ? $people[$back]->id : null,
                        'tester_id' => $test ? $people[$test]->id : null,
                        'approval_status' => $type->needsApproval() ? 'pending' : 'not_required',
                        'reported_at' => $reportedAt,
                        'first_response_at' => $reportedAt->addHours(2),
                        'sla_due_at' => $sla->dueAt($priority, $reportedAt),
                        'resolved_at' => $resolved ? $reportedAt->addDays(1) : null,
                    ]
                );

                $this->seedWorkLogs($ticket, $status);
                $this->seedHistory($ticket, $status, $reportedAt, $support->id);
            });
        }
    }

    /**
     * Work logs consistent with the ticket's status — a ticket sitting in
     * in_progress must have a started side, or the board would show a "بدأت"
     * button on work that already began. F07
     */
    private function seedWorkLogs(Ticket $ticket, TicketStatusValue $status): void
    {
        $logStatus = match ($status) {
            TicketStatusValue::for('assigned') => 'pending',
            TicketStatusValue::for('in_progress') => 'in_progress',
            TicketStatusValue::for('dev_done'), TicketStatusValue::for('testing'), TicketStatusValue::for('resolved'), TicketStatusValue::for('closed') => 'done',
            default => null,
        };

        if ($logStatus === null) {
            return;
        }

        foreach (WorkSide::cases() as $side) {
            $userId = $ticket->{$side->assigneeColumn()};

            if ($userId === null) {
                continue;
            }

            TicketWorkLog::updateOrCreate(
                ['ticket_id' => $ticket->id, 'side' => $side->value],
                [
                    'user_id' => $userId,
                    'status' => $logStatus,
                    'started_at' => $logStatus === 'pending' ? null : $ticket->reported_at->addHours(3),
                    'finished_at' => $logStatus === 'done' ? $ticket->reported_at->addHours(20) : null,
                    'duration_minutes' => $logStatus === 'done' ? 17 * 60 : null,
                ]
            );
        }
    }

    private function seedHistory(Ticket $ticket, TicketStatusValue $status, CarbonImmutable $reportedAt, int $userId): void
    {
        if ($ticket->statusHistory()->exists()) {
            return;
        }

        $ticket->statusHistory()->create([
            'from_status' => null,
            'to_status' => TicketStatusValue::for('new')->value,
            'user_id' => $userId,
            'note' => null,
            'created_at' => $reportedAt,
        ]);

        if ($status !== TicketStatusValue::for('new')) {
            $ticket->statusHistory()->create([
                'from_status' => TicketStatusValue::for('new')->value,
                'to_status' => $status->value,
                'user_id' => $userId,
                'created_at' => $reportedAt->addHours(4),
            ]);
        }
    }
}
