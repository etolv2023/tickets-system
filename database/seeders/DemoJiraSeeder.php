<?php

namespace Database\Seeders;

use App\Enums\SubtaskSide;
use App\Models\Label;
use App\Models\Ticket;
use App\Models\TicketLink;
use App\Models\User;
use App\Services\SubtaskService;
use App\Services\TimeTrackingService;
use Illuminate\Database\Seeder;

/**
 * Development only. Subtasks, logged time, labels and links — including the two
 * cases that actually matter: a side whose subtasks aren't done (so "خلصت" is
 * blocked, F07) and a ticket blocked by another (F10).
 */
class DemoJiraSeeder extends Seeder
{
    public function run(): void
    {
        $subtasks = app(SubtaskService::class);
        $time = app(TimeTrackingService::class);

        $admin = User::where('email', 'admin@etolv.test')->first();
        $frontend = User::where('email', 'frontend@etolv.test')->first();
        $backend = User::where('email', 'backend@etolv.test')->first();
        $fullstack = User::where('email', 'fullstack@etolv.test')->first();

        $this->seedLabels($admin->id);

        // Role-based assignment (2026-07-24): find work by role assignments.
        $roleIds = \App\Models\Role::query()
            ->whereIn('key', ['frontend', 'backend'])
            ->pluck('id', 'key');

        // A both-sides ticket: give the backend an unfinished subtask so the
        // F07 gate has something real to refuse.
        $both = Ticket::query()
            ->whereHas('roleAssignments', fn ($q) => $q->where('role_id', $roleIds['frontend'] ?? 0))
            ->whereHas('roleAssignments', fn ($q) => $q->where('role_id', $roleIds['backend'] ?? 0))
            ->first();

        if ($both !== null) {
            $rows = [
                ['عدّل الفاليديشن على الفورم', SubtaskSide::Frontend, 'done', $frontend, 3, -4],
                ['صلّح استعلام الأصناف البطيء', SubtaskSide::Backend, 'in_progress', $backend, 6, 2],
                ['اظبط الـ pagination', SubtaskSide::Backend, 'todo', $backend, 2, 4],
                ['اختبر على 10 آلاف صنف', SubtaskSide::Qa, 'todo', null, 1, 5],
            ];

            foreach ($rows as [$title, $side, $status, $assignee, $estimate, $dueOffset]) {
                $subtask = $subtasks->create($both, [
                    'title' => $title,
                    'side' => $side->value,
                    'status' => $status,
                    'assignee_id' => $assignee?->id,
                    'start_date' => now()->addDays($dueOffset - 2)->toDateString(),
                    'due_date' => now()->addDays($dueOffset)->toDateString(),
                    'estimated_hours' => $estimate,
                    'completed_at' => $status === 'done' ? now()->subDay() : null,
                ], $admin->id);

                if ($assignee !== null && $status !== 'todo') {
                    $time->log($both, [
                        'subtask_id' => $subtask->id,
                        'hours' => $status === 'done' ? $estimate : round($estimate / 2, 2),
                        'spent_on' => now()->subDays(1)->toDateString(),
                        'note' => 'شغل على ' . $title,
                    ], $assignee->id);
                }
            }
        }

        // A ticket that overran its estimate, so the amber/red bar has a case.
        $overrun = Ticket::where('status', 'in_progress')->where('id', '!=', $both?->id)->first();

        $overrunBackendId = $overrun
            ? $overrun->assigneeIdForRole($roleIds['backend'] ?? 0)
            : null;

        if ($overrun !== null && $overrunBackendId !== null) {
            $subtask = $subtasks->create($overrun, [
                'title' => 'اتتبع مصدر الضريبة المضاعفة',
                'side' => SubtaskSide::Backend->value,
                'status' => 'in_progress',
                'assignee_id' => $overrunBackendId,
                'due_date' => now()->subDays(2)->toDateString(),
                'estimated_hours' => 4,
            ], $admin->id);

            // Twice the estimate — this is the red case.
            $time->log($overrun, [
                'subtask_id' => $subtask->id,
                'hours' => 8.5,
                'spent_on' => now()->subDays(2)->toDateString(),
                'note' => 'أطول من المتوقع',
            ], $overrunBackendId);
        }

        $this->seedLinks($admin->id);
    }

    private function seedLabels(int $adminId): void
    {
        $labels = [
            'عاجل للعميل' => 'red',
            'ريجريشن' => 'red',
            // Teal, not blue — blue is reserved for interactive chrome and the
            // seeder runs AFTER the blue→teal remap migration, so a blue row
            // here would sneak the forbidden hue back in.
            'محتاج تصميم' => 'teal',
            'أداء' => 'green',
            'مستني رد العميل' => 'amber',
        ];

        foreach ($labels as $name => $color) {
            Label::updateOrCreate(['name' => $name], ['color' => $color, 'created_by' => $adminId]);
        }

        $tickets = Ticket::orderBy('id')->limit(4)->get();
        $ids = Label::pluck('id')->all();

        foreach ($tickets as $i => $ticket) {
            $ticket->labels()->sync([$ids[$i % count($ids)]]);
        }
    }

    /** F10: a real blocking pair, so the blocked marker has something to show. */
    private function seedLinks(int $adminId): void
    {
        $tickets = Ticket::orderBy('id')->limit(3)->get();

        if ($tickets->count() < 3) {
            return;
        }

        TicketLink::updateOrCreate(
            [
                'from_ticket_id' => $tickets[0]->id,
                'to_ticket_id' => $tickets[1]->id,
                'type' => 'blocks',
            ],
            ['created_by' => $adminId]
        );

        TicketLink::updateOrCreate(
            [
                'from_ticket_id' => $tickets[2]->id,
                'to_ticket_id' => $tickets[0]->id,
                'type' => 'relates',
            ],
            ['created_by' => $adminId]
        );
    }
}
