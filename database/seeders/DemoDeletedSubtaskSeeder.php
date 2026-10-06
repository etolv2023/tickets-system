<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketWorkLog;
use App\Models\User;
use App\Services\SubtaskService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reproduction fixture for a ticket left in progress after its last open
 * subtask is soft-deleted. Run explicitly; DatabaseSeeder does not call it.
 */
class DemoDeletedSubtaskSeeder extends Seeder
{
    public function run(SubtaskService $subtasks): void
    {
        DB::transaction(function () use ($subtasks) {
            $roles = Role::query()
                ->where('logs_work', true)
                ->orderBy('id')
                ->limit(2)
                ->get();

            if ($roles->count() !== 2) {
                throw new \RuntimeException('The reproduction needs two work-logging roles.');
            }

            $assignees = $roles->mapWithKeys(fn (Role $role) => [
                $role->id => User::query()->where('role_id', $role->id)->firstOrFail(),
            ]);
            $creator = User::query()->orderBy('id')->firstOrFail();

            $ticket = Ticket::create([
                'ticket_number' => 'DEL-' . now()->format('ymdHis'),
                'reporter_name' => 'حالة حذف صب تاسك',
                'title' => 'إعادة إنتاج بلوك الإغلاق بعد حذف صب تاسك',
                'description' => '<p>بيانات تجريبية معزولة لتتبع بوابة الإغلاق.</p>',
                'type' => 'bug',
                'priority' => 'medium',
                'status' => 'in_progress',
                'reported_at' => now()->subDay(),
                'created_by' => $creator->id,
                'approval_status' => 'not_required',
            ]);

            foreach ($roles as $role) {
                $assignee = $assignees[$role->id];

                $ticket->roleAssignments()->create([
                    'role_id' => $role->id,
                    'user_id' => $assignee->id,
                ]);

                TicketWorkLog::create([
                    'ticket_id' => $ticket->id,
                    'role_id' => $role->id,
                    'user_id' => $assignee->id,
                    'status' => 'done',
                    'started_at' => now()->subHours(4),
                    'finished_at' => now()->subHour(),
                    'duration_minutes' => 180,
                ]);
            }

            $firstRole = $roles->first();
            $secondRole = $roles->last();

            $deleted = $subtasks->create($ticket, [
                'title' => 'صب تاسك مفتوحة ثم محذوفة',
                'role_id' => $firstRole->id,
                'assignee_id' => $assignees[$firstRole->id]->id,
                'status' => 'todo',
            ], $creator->id);

            foreach ([
                [$firstRole, 'صب تاسك مكتملة للجهة الأولى'],
                [$secondRole, 'صب تاسك مكتملة للجهة الثانية'],
            ] as [$role, $title]) {
                $subtasks->create($ticket, [
                    'title' => $title,
                    'role_id' => $role->id,
                    'assignee_id' => $assignees[$role->id]->id,
                    'status' => 'done',
                    'completed_at' => now()->subHour(),
                ], $creator->id);
            }

            $subtasks->delete($deleted);

            $this->command?->info(
                "  {$ticket->ticket_number}: in_progress with two done work logs; the only open subtask is soft-deleted."
            );
        });
    }
}
