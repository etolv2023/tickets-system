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
                <x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge>
                <x-badge variant="neutral">{{ $subtask->side->label() }}</x-badge>

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
                        @if ($subtask->isOverdue()) ⚠ @endif
                    </span>
                @endif

                @if ($subtask->estimated_hours)
                    <span class="u-mono">
                        {{ rtrim(rtrim($subtask->spent_hours, '0'), '.') }}/{{ rtrim(rtrim($subtask->estimated_hours, '0'), '.') }} س
                    </span>
                @endif
            </div>

            @if ($subtask->blocked_reason)
                <p class="subtask__reason">متوقفة: {{ $subtask->blocked_reason }}</p>
            @endif
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
