{{-- The four F22.1 questions, in priority order, as a masonry (today.css:
     .today { columns: 2 }). A one-row or cleared section packs tight beneath
     the section above it in the same column instead of leaving a canyon beside
     a tall list — the definitive fix for "كروت كبيرة مليانة هوا" (CLAUDE.md § 6).
     Every empty state is a single-row .today__clear strip, so a cleared section
     is the height of one row, never a padded void. Same variables, @can gates
     and row markup as before — this is a layout + empty-state change only. The
     top KPI row moved to the header pulse-strip (_hero). --}}
<div class="today">
    <x-today-section question="الي بيولّع" hint="تذاكر خرقت مهلة الـ SLA"
                     :count="$counts['onFire']" variant="red" anchor="sec-onfire">
        <x-slot:action>
            <a class="today__q-hint" href="{{ route('tickets.index', ['status' => 'open']) }}">عرض الكل</a>
        </x-slot:action>

        @forelse ($onFire as $ticket)
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
                    عدّى بـ {{ $ticket->sla_due_at->diffForHumans(null, true) }}
                </x-badge>
            </a>
        @empty
            <div class="today__clear">
                <x-icon name="check-circle" class="today__clear-glyph" />
                مفيش خرق للمهلة. تمام.
            </div>
        @endforelse
    </x-today-section>

    <x-today-section question="الي على دماغي" hint="صب تاسكس مستحقة النهاردة أو متأخرة"
                     :count="$counts['onMyPlate']" variant="green" anchor="sec-plate">
        @forelse ($onMyPlate as $subtask)
            <a class="today__row" href="{{ route('tickets.show', $subtask->ticket_id) }}">
                <x-priority-stripe :priority="$subtask->ticket->priority" />
                <div class="today__row-main">
                    <span class="today__row-title">{{ $subtask->title }}</span>
                    <div class="today__row-meta">
                        <span class="u-mono u-ltr">{{ $subtask->ticket->ticket_number }}</span>
                        · {{ $subtask->ticket->originLabel() }}
                        @if ($subtask->estimated_hours)
                            · {{ rtrim(rtrim($subtask->estimated_hours, '0'), '.') }} س
                        @endif
                    </div>
                </div>
                <span @class(['tickets__age', 'tickets__age--overdue' => $subtask->isOverdue()])>
                    {{ $subtask->isOverdue()
                        ? 'متأخرة ' . $subtask->due_date->diffForHumans(null, true)
                        : 'النهاردة' }}
                </span>
            </a>
        @empty
            <div class="today__clear">
                <x-icon name="check-circle" class="today__clear-glyph" />
                مفيش حاجة مستحقة عليك.
            </div>
        @endforelse
    </x-today-section>

    @can('features.approve')
        <x-today-section question="مستني قراري" hint="فيتشرات وموديولات مستنية موافقتك"
                         :count="$counts['awaitingMe']" variant="amber" anchor="sec-approve">
            @forelse ($awaitingMe as $ticket)
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
                    مفيش حاجة مستنية قرارك.
                </div>
            @endforelse
        </x-today-section>
    @endcan

    <x-today-section question="أنا مأخّر مين" hint="تذاكر شغلك بيبلوكها"
                     :count="$counts['blockingOthers']" variant="teal" anchor="sec-blocking">
        @forelse ($blockingOthers as $ticket)
            <a class="today__row" href="{{ route('tickets.show', $ticket) }}">
                <x-priority-stripe :priority="$ticket->priority" />
                <div class="today__row-main">
                    <span class="today__row-title">{{ $ticket->title }}</span>
                    <div class="today__row-meta">
                        بتبلوك:
                        @foreach ($ticket->outgoingLinks->filter(fn ($l) => $l->type->isBlocks()) as $link)
                            @if ($link->toTicket && $link->toTicket->status->isOpen())
                                <span class="u-mono u-ltr">{{ $link->toTicket->ticket_number }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                <span class="blocked-flag">مبلوكة بيك</span>
            </a>
        @empty
            <div class="today__clear">
                <x-icon name="check-circle" class="today__clear-glyph" />
                مش مأخّر حد.
            </div>
        @endforelse
    </x-today-section>
</div>
