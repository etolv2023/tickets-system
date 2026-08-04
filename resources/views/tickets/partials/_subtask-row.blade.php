@php $done = $subtask->status->isDone(); @endphp

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
                @php
                    $quick = \App\Models\SubtaskStatusDefinition::quickChangeKeys();
                    // Covers two cases at once: a reason-requiring status (never
                    // quick-changeable), and a status whose definition an admin
                    // deleted — a select with no matching option would silently
                    // display the FIRST status instead of the real one.
                    $asBadge = ! in_array($subtask->status->value, $quick, true);
                @endphp

                @if ($asBadge)
                    <x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge>
                @else
                    @php $mayChange = auth()->user()->can('update', [$subtask, $ticket]); @endphp

                    {{-- ★ (2026-08-02) Someone else's subtask shows the SAME
                         control, disabled — not a badge.

                         On a ticket several roles share, swapping the control
                         out for a badge made the rows look like two different
                         kinds of thing, and you couldn't tell "this isn't mine"
                         from "this one can't be changed at all". One control in
                         two states says both: the status is readable in the
                         same place on every row, and the greyed box says whose
                         it is without a word of explanation. --}}
                    <select class="select select--sm" aria-label="حالة الصب تاسك"
                            @disabled(! $mayChange)
                            @if ($mayChange)
                                data-subtask-status="{{ route('subtasks.status', $subtask) }}"
                                data-current="{{ $subtask->status->value }}"
                            @else
                                title="{{ $subtask->assignee ? "الصب تاسك دي بتاعة {$subtask->assignee->name}" : 'مش من حقك تغيّر الصب تاسك دي' }}"
                            @endif>
                        @foreach ($quick as $optionKey)
                            <option value="{{ $optionKey }}" @selected($subtask->status->value === $optionKey)>{{ \App\Casts\SubtaskStatusValue::for($optionKey)->label() }}</option>
                        @endforeach
                    </select>
                @endif

                {{-- A subtask is categorised by its role now (2026-07-24); an
                     untagged one is general work. --}}
                @if ($subtask->role)
                    <x-badge variant="neutral">{{ $subtask->role->name_ar }}</x-badge>
                @endif

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
                    {{-- ★ (2026-08-05) الرقم بيتقال بإشارته. صب تاسك ليها تاريخ
                         استحقاق وخلصت بعده بتتسجل في دفتر النقاط بالسالب
                         (PointEngineService)، فعرض الموجب هنا كان هيوعد بحاجة
                         الصرف مش هيديها. لسه مخلصتش؟ نفس التحذير — يعني
                         «لو خلصتها دلوقتي دي تكلفتها». --}}
                    @php $costs = $subtask->finishedLate(); @endphp

                    <span @class(['points-cell', 'points-cell--negative' => $costs])
                          title="{{ $costs
                                ? 'اتأخرت عن تاريخ الاستحقاق — النقط دي هتتخصم مش هتتصرف لما التذكرة تتحل'
                                : 'النقاط الي هتتصرف لما التذكرة تتحل' }}">
                        {{ $costs ? '−' : '' }}{{ rtrim(rtrim($subtask->points, '0'), '.') }} نقطة
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

                {{-- Its own permission since 2026-08-02: deleting destroys the
                     record F18 would have paid on, which is not the same act as
                     editing your own step. --}}
                @can('delete', [$subtask, $ticket])
                    <form method="POST" action="{{ route('tickets.subtasks.destroy', [$ticket, $subtask]) }}"
                          onsubmit="return confirm('متأكد إنك عايز تحذف «{{ $subtask->title }}»؟')">
                        @csrf
                        @method('DELETE')
                        <x-button variant="ghost" size="sm">حذف</x-button>
                    </form>
                @endcan
            </div>
        </div>
    @endcan
</div>
