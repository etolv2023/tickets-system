{{-- The four F22.1 questions, in priority order, as a masonry (today.css:
     .today { columns: 2 }). A one-row or cleared section packs tight beneath
     the section above it in the same column instead of leaving a canyon beside
     a tall list — the definitive fix for "كروت كبيرة مليانة هوا" (CLAUDE.md § 6).
     Every empty state is a single-row .today__clear strip, so a cleared section
     is the height of one row, never a padded void. Same variables, @can gates
     and row markup as before — this is a layout + empty-state change only. The
     top KPI row moved to the header pulse-strip (_hero). --}}
<div class="today">
    <x-today-section :question="__('ui.today.overdue_title')" :hint="__('ui.today.overdue_hint')" class="today__section--focus"
                     :count="$counts['onFire']" variant="red" anchor="sec-onfire">
        <x-slot:action>
            <a class="today__q-hint" href="{{ route('tickets.index', ['status' => 'open']) }}">{{ __('ui.common.view_all') }}</a>
        </x-slot:action>

        @forelse ($onFire->take(6) as $ticket)
            <a class="today__row" href="{{ route('tickets.show', $ticket) }}">
                <x-priority-stripe :priority="$ticket->priority" />
                <div class="today__row-main">
                    <span class="today__row-title">{{ $ticket->title }}</span>
                    <div class="today__row-meta">
                        <span class="u-mono u-ltr">{{ $ticket->ticket_number }}</span>
                        · {{ $ticket->originLabel() }}
                    </div>
                </div>
                <x-badge variant="red">
                    {{ __('ui.today.overdue_by', ['duration' => $ticket->sla_due_at->diffForHumans(null, true)]) }}
                </x-badge>
            </a>
        @empty
            <div class="today__clear">
                <x-icon name="check-circle" class="today__clear-glyph" />
                {{ __('ui.today.no_overdue') }}
            </div>
        @endforelse
    </x-today-section>

    <x-today-section :question="__('ui.today.due_title')" :hint="__('ui.today.due_hint')" class="today__section--plate"
                     :count="$counts['onMyPlate']" variant="green" anchor="sec-plate">
        <x-slot:action>
            <a class="today__q-hint" href="{{ route('calendar.mine', ['view' => 'day']) }}">{{ __('ui.today.open_calendar') }}</a>
        </x-slot:action>
        @forelse ($onMyPlate->take(4) as $subtask)
            <a class="today__row" href="{{ route('tickets.show', $subtask->ticket_id) }}">
                <x-priority-stripe :priority="$subtask->ticket->priority" />
                <div class="today__row-main">
                    <span class="today__row-title">{{ $subtask->title }}</span>
                    <div class="today__row-meta">
                        <span class="u-mono u-ltr">{{ $subtask->ticket->ticket_number }}</span>
                        · {{ $subtask->ticket->originLabel() }}
                        @if ($subtask->estimated_hours)
                            · {{ rtrim(rtrim($subtask->estimated_hours, '0'), '.') }} {{ __('ui.today.hours_short') }}
                        @endif
                    </div>
                </div>
                <span @class(['tickets__age', 'tickets__age--overdue' => $subtask->isOverdue()])>
                    {{ $subtask->isOverdue()
                        ? __('ui.today.overdue_prefix') . ' ' . $subtask->due_date->diffForHumans(null, true)
                        : __('ui.today.due_today') }}
                </span>
            </a>
        @empty
            <div class="today__clear">
                <x-icon name="check-circle" class="today__clear-glyph" />
                {{ __('ui.today.no_due_tasks') }}
            </div>
        @endforelse
    </x-today-section>

    @can('features.approve')
        <x-today-section :question="__('ui.today.approval_title')" :hint="__('ui.today.approval_hint')" class="today__section--approve"
                         :count="$counts['awaitingMe']" variant="amber" anchor="sec-approve">
            <x-slot:action>
                <a class="today__q-hint" href="{{ route('queues.approvals') }}">{{ __('ui.common.all_approvals') }}</a>
            </x-slot:action>
            @forelse ($awaitingMe->take(4) as $ticket)
                <a class="today__row" href="{{ route('tickets.show', $ticket) }}">
                    <x-priority-stripe :priority="$ticket->priority" />
                    <div class="today__row-main">
                        <span class="today__row-title">{{ $ticket->title }}</span>
                        <div class="today__row-meta">
                            <span class="u-mono u-ltr">{{ $ticket->ticket_number }}</span>
                            · {{ $ticket->originLabel() }}
                        </div>
                    </div>
                    <x-badge :variant="$ticket->type->variant()" :icon="$ticket->type->icon()">{{ $ticket->type->label() }}</x-badge>
                </a>
            @empty
                <div class="today__clear">
                    <x-icon name="check-circle" class="today__clear-glyph" />
                    {{ __('ui.today.no_approvals') }}
                </div>
            @endforelse
        </x-today-section>
    @endcan

    <x-today-section :question="__('ui.today.blocking_title')" :hint="__('ui.today.blocking_hint')" class="today__section--blocking"
                     :count="$counts['blockingOthers']" variant="teal" anchor="sec-blocking">
        @forelse ($blockingOthers->take(4) as $ticket)
            <a class="today__row" href="{{ route('tickets.show', $ticket) }}">
                <x-priority-stripe :priority="$ticket->priority" />
                <div class="today__row-main">
                    <span class="today__row-title">{{ $ticket->title }}</span>
                    <div class="today__row-meta">
                        {{ __('ui.today.blocks') }}:
                        @foreach ($ticket->outgoingLinks->filter(fn ($l) => $l->type->isBlocks()) as $link)
                            @if ($link->toTicket && $link->toTicket->status->isOpen())
                                <span class="u-mono u-ltr">{{ $link->toTicket->ticket_number }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                <span class="blocked-flag">{{ __('ui.today.blocked_by_you') }}</span>
            </a>
        @empty
            <div class="today__clear">
                <x-icon name="check-circle" class="today__clear-glyph" />
                {{ __('ui.today.no_blocking') }}
            </div>
        @endforelse
    </x-today-section>
</div>
