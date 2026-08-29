{{-- One scheduled task: what it is, when it runs, how the last run went, and
     the controls for all three.

     A card rather than a table row, for the same reason the GitHub repositories
     are cards: the row carries an editor with five fields, and five editable
     fields do not survive a table cell on a phone.

     The Arabic name, the description and «بتمس النقاط» all come from
     ScheduledTaskRegistry — the row in the database holds a key, a cron
     expression and a switch, and nothing that describes what the task does. --}}

@php
    // Presentation only: which parts of the cron the dropdowns should show as
    // selected. The parsing itself is CronPreset's job, not this file's.
    $parts = \App\Support\CronPreset::fromExpression($task->cron);
    $next = $task->nextRunAt();
@endphp

<x-card>
    <x-slot:actions>
        <form method="POST" action="{{ route('admin.scheduled-tasks.run', $task) }}">
            @csrf
            <x-button variant="ghost" size="sm">
                <x-icon name="refresh" class="btn__icon" />
                شغّلها دلوقتي
            </x-button>
        </form>
    </x-slot:actions>

    <div class="stack stack--tight">
        <div class="cron-head">
            <span class="cron-head__name">{{ $task->name() }}</span>
            <span class="u-mono u-ltr cron-head__key">{{ $task->key }}</span>

            @if ($task->is_enabled)
                <x-badge variant="green">شغّالة</x-badge>
            @else
                <x-badge variant="red">مقفولة</x-badge>
            @endif

            @if ($task->touchesPoints())
                <x-badge variant="amber">بتمس النقاط</x-badge>
            @endif
        </div>

        <p class="field__hint">{{ $task->definition()['description'] ?? '' }}</p>

        <div class="cron-facts">
            <span>
                الميعاد: <strong>{{ \App\Support\CronPreset::describe($task->cron) }}</strong>
                <span class="u-mono u-ltr u-subtle">{{ $task->cron }}</span>
            </span>

            <span>
                المرة الجاية:
                @if ($next)
                    <span class="u-mono">{{ $next->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}</span>
                @else
                    <span class="u-subtle">—</span>
                @endif
            </span>

            <span>
                آخر تشغيل:
                @if (! $task->hasRun())
                    <span class="u-subtle">لسه ماشتغلتش</span>
                @else
                    <span class="u-mono">{{ $task->last_started_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}</span>

                    @if ($task->last_finished_at === null)
                        <x-badge variant="blue">بتشتغل دلوقتي</x-badge>
                    @elseif ($task->lastRunSucceeded())
                        <x-badge variant="green">نجحت</x-badge>
                    @else
                        <x-badge variant="red">فشلت</x-badge>
                    @endif

                    @if ($task->last_duration_ms !== null)
                        <span class="u-mono u-subtle">{{ number_format($task->last_duration_ms / 1000, 1) }}ث</span>
                    @endif
                @endif
            </span>
        </div>

        @if (filled($task->last_output))
            <details class="cron-output">
                <summary class="cron-output__summary">مخرجات آخر تشغيل</summary>
                <pre class="cron-output__body u-ltr">{{ $task->last_output }}</pre>
            </details>
        @endif

        @include('admin.scheduled-tasks.partials._schedule-form', ['task' => $task, 'parts' => $parts])
    </div>
</x-card>
