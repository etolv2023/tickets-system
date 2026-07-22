@php $done = $subtask->status === \App\Enums\SubtaskStatus::Done; @endphp

<div class="subtask" data-subtask-id="{{ $subtask->id }}" x-data="{ editing: false }">
    @can('update', [$subtask, $ticket])
        <span class="subtask__handle" data-drag-handle title="اسحب لإعادة الترتيب">
            <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M7 4a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm9-12a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm0 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z" />
            </svg>
        </span>
    @endcan

    <div class="subtask__main">
        <div x-show="!editing">
            <div @class(['subtask__title', 'subtask__title--done' => $done])>{{ $subtask->title }}</div>

            <div class="subtask__meta">
                {{-- A blocked subtask keeps the badge: unblocking needs the
                     reason cleared, which only the full edit form can do, and
                     a select without a "blocked" option would silently show
                     the wrong value. --}}
                @can('update', [$subtask, $ticket])
                    @if ($subtask->status !== \App\Enums\SubtaskStatus::Blocked)
                        <select class="select select--sm" aria-label="حالة الصب تاسك"
                                data-subtask-status="{{ route('subtasks.status', $subtask) }}"
                                data-current="{{ $subtask->status->value }}">
                            @foreach ([\App\Enums\SubtaskStatus::Todo, \App\Enums\SubtaskStatus::InProgress, \App\Enums\SubtaskStatus::Done] as $option)
                                <option value="{{ $option->value }}" @selected($subtask->status === $option)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                    @else
                        <x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge>
                    @endif
                @else
                    <x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge>
                @endcan

                {{-- F06 role-assignment extension: a role-tagged subtask shows
                     the role name instead of the underlying "أخرى" side. --}}
                <x-badge variant="neutral">{{ $subtask->role?->name_ar ?? $subtask->side->label() }}</x-badge>

                @if ($subtask->assignee)
                    <span class="row">
                        <x-avatar :user="$subtask->assignee" size="sm" />
                        {{ $subtask->assignee->name }}
                    </span>
                @else
                    <span class="u-subtle">مش مسندة</span>
                @endif

                @if ($subtask->due_date)
                    <span @class(['subtask__date--overdue' => $subtask->isOverdue()])>
                        استحقاق {{ $subtask->due_date->translatedFormat('j M') }}
                        @if ($subtask->isOverdue())
                            <x-icon name="alert" size="0.9em" />
                        @endif
                    </span>
                @endif

                @if ($subtask->points > 0)
                    <span class="points-cell" title="النقاط الي هتتصرف لما التذكرة تتحل">
                        {{ rtrim(rtrim($subtask->points, '0'), '.') }} نقطة
                    </span>
                @endif

                @if ($subtask->estimated_hours)
                    {{-- Over the estimate the pair turns amber: the number is
                         the warning, so no icon is needed beside it. --}}
                    <span @class([
                        'subtask__hours',
                        'subtask__hours--over' => (float) $subtask->spent_hours > (float) $subtask->estimated_hours,
                    ])>
                        {{ rtrim(rtrim($subtask->spent_hours, '0'), '.') }}/{{ rtrim(rtrim($subtask->estimated_hours, '0'), '.') }} س
                    </span>
                @endif
            </div>

            @if ($subtask->blocked_reason)
                <p class="subtask__reason">متوقفة: {{ $subtask->blocked_reason }}</p>
            @endif

            {{-- Where a refused status change reports itself. Empty until the
                 server says no — an alert() would steal focus mid-scan. --}}
            <p class="subtask__message" data-subtask-message role="status" aria-live="polite"></p>
        </div>

        {{-- Inline editing: adding and changing a subtask never leaves the
             ticket page. F08 --}}
        <div x-show="editing" x-cloak>
            <form method="POST" action="{{ route('tickets.subtasks.update', [$ticket, $subtask]) }}">
                @csrf
                @method('PUT')
                @include('tickets.partials._subtask-fields', ['subtask' => $subtask])

                <div class="form-actions">
                    <x-button variant="primary" size="sm">حفظ</x-button>
                    <x-button variant="ghost" size="sm" type="button" @click="editing = false">إلغاء</x-button>
                </div>
            </form>
        </div>
    </div>

    @can('update', [$subtask, $ticket])
        <div class="subtask__actions" x-show="!editing">
            <div class="row">
                <x-button variant="ghost" size="sm" type="button" @click="editing = true">تعديل</x-button>

                <form method="POST" action="{{ route('tickets.subtasks.destroy', [$ticket, $subtask]) }}"
                      onsubmit="return confirm('متأكد إنك عايز تحذف «{{ $subtask->title }}»؟')">
                    @csrf
                    @method('DELETE')
                    <x-button variant="ghost" size="sm">حذف</x-button>
                </form>
            </div>
        </div>
    @endcan
</div>
