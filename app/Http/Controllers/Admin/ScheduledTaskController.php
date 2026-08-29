<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ScheduledTaskRequest;
use App\Jobs\RunScheduledTask;
use App\Models\ScheduledTask;
use App\Services\ActivityLogger;
use App\Support\CronPreset;
use App\Support\ScheduledTaskRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ★ (2026-08-29) /admin/scheduled-tasks — the cron jobs, and whether they ran.
 *
 * Three things a person can do here: move when a task runs, switch it off, and
 * run it once now. There is deliberately no fourth: no adding a task, no
 * deleting one, and above all no typing a command. The list is
 * ScheduledTaskRegistry, which is code.
 *
 * The screen also answers the question the schedule itself cannot: is anything
 * executing this at all. Without the heartbeat, a server whose system cron was
 * never installed shows a perfectly configured page and runs nothing.
 */
class ScheduledTaskController extends Controller
{
    public function index(): View
    {
        // Registry order, not database order — the code is the list, the rows
        // are configuration hanging off it. Anything in the table with no
        // registry entry is not shown, for the same reason it is not scheduled.
        $rows = ScheduledTask::all()->keyBy('key');

        $tasks = collect(ScheduledTaskRegistry::keys())
            ->map(fn (string $key) => $rows->get($key))
            ->filter()
            ->values();

        return view('admin.scheduled-tasks.index', [
            'tasks' => $tasks,
            // A task in the registry with no row is never scheduled and never
            // listed. Saying so beats leaving a silent gap.
            'unseeded' => array_values(array_diff(ScheduledTaskRegistry::keys(), $rows->keys()->all())),
            'cronIsAlive' => ScheduledTask::cronIsAlive(),
            'lastHeartbeat' => ScheduledTask::lastHeartbeat(),
            'frequencies' => CronPreset::FREQUENCIES,
            'weekdays' => CronPreset::WEEKDAYS,
        ]);
    }

    public function update(
        ScheduledTaskRequest $request,
        ScheduledTask $scheduledTask,
        ActivityLogger $logger,
    ): RedirectResponse {
        $before = $scheduledTask->only('cron', 'is_enabled');

        $scheduledTask->update([
            'cron' => $request->cronExpression(),
            'is_enabled' => $request->boolean('is_enabled'),
        ]);

        // Switching off a scheduled task is invisible by nature — nothing
        // happens, and nothing happening looks like nothing being wrong. It is
        // logged so "the penalties stopped in September" has an answer.
        $logger->log(
            action: 'schedule.updated',
            userId: $request->user()->id,
            subject: $scheduledTask,
            changes: ['from' => $before, 'to' => $scheduledTask->only('cron', 'is_enabled')],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', $scheduledTask->is_enabled
            ? 'اتحفظ. المهمة هتشتغل ' . CronPreset::describe($scheduledTask->cron) . '.'
            : 'اتحفظ. المهمة مقفولة دلوقتي ومش هتشتغل لوحدها.');
    }

    /**
     * Run it once, now. Queued — these are the commands that take minutes.
     *
     * Allowed even on a disabled task: "شغّلها مرة" is exactly what somebody
     * does after switching one off to investigate, and refusing would send them
     * to a terminal to do the same thing with less of a record.
     */
    public function run(Request $request, ScheduledTask $scheduledTask, ActivityLogger $logger): RedirectResponse
    {
        if (! ScheduledTaskRegistry::has($scheduledTask->key)) {
            return back()->withErrors(['run' => 'المهمة دي مش موجودة في الكود، فمش هتشتغل.']);
        }

        RunScheduledTask::dispatch($scheduledTask->key);

        $logger->log(
            action: 'schedule.ran_manually',
            userId: $request->user()->id,
            subject: $scheduledTask,
            changes: ['to' => ['key' => $scheduledTask->key]],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return back()->with('status', '«' . $scheduledTask->name() . '» اتحطت في الطابور. حدّث الصفحة بعد شوية عشان تشوف النتيجة.');
    }
}
