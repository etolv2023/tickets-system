@extends('layouts.app')

@section('title', 'اليوم')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="today__greeting">أهلاً، {{ $user->name }}</h1>
                <p class="today__date">
                    دي الحاجات الي محتاجة منك حركة النهاردة — مش أرقام، شغل.
                    · {{ now(config('app.display_timezone'))->translatedFormat('l j F Y') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- Each tile counts the list below it — an index into work, not a
             score. The hue is the same one the list's rows carry. --}}
        <div class="today-stats">
            <div class="stat-tile stat-tile--red">
                <div class="stat-tile__figure">{{ $counts['onFire'] }}</div>
                <div class="stat-tile__caption">تذاكر خرقت مهلة الـ SLA</div>
            </div>

            @can('features.approve')
                <div class="stat-tile stat-tile--amber">
                    <div class="stat-tile__figure">{{ $counts['awaitingMe'] }}</div>
                    <div class="stat-tile__caption">مستنية موافقتك</div>
                </div>
            @endcan

            <div class="stat-tile stat-tile--teal">
                <div class="stat-tile__figure">{{ $counts['blockingOthers'] }}</div>
                <div class="stat-tile__caption">تذاكر شغلك بيبلوكها</div>
            </div>

            <div class="stat-tile stat-tile--green">
                <div class="stat-tile__figure">{{ $counts['onMyPlate'] }}</div>
                <div class="stat-tile__caption">صب تاسكس مستحقة عليك</div>
            </div>
        </div>

        {{-- The four questions from F22.1, in the order they matter. No totals
             row, no charts — the numbers live at /reports and you go to them
             on purpose (CLAUDE.md § 6). --}}
        {{-- The wide column carries what is already burning; the narrow one is
             the checklist you work off. Whole rows are the hit target. --}}
        <div class="today">
            <x-today-section question="الي بيولّع" hint="تذاكر خرقت مهلة الـ SLA">
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
                    <p class="card__empty">مفيش خرق للمهلة.</p>
                @endforelse
            </x-today-section>

            <x-today-section question="الي على دماغي" hint="صب تاسكس مستحقة النهاردة أو متأخرة">
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
                    <p class="card__empty">مفيش حاجة مستحقة عليك.</p>
                @endforelse
            </x-today-section>

            @can('features.approve')
                <x-today-section question="مستني قراري" hint="فيتشرات وموديولات مستنية موافقتك">
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
                        <p class="card__empty">مفيش حاجة مستنية قرارك.</p>
                    @endforelse
                </x-today-section>
            @endcan

            <x-today-section question="أنا مأخّر مين" hint="تذاكر شغلك بيبلوكها">
                @forelse ($blockingOthers as $ticket)
                    <a class="today__row" href="{{ route('tickets.show', $ticket) }}">
                        <x-priority-stripe :priority="$ticket->priority" />
                        <div class="today__row-main">
                            <span class="today__row-title">{{ $ticket->title }}</span>
                            <div class="today__row-meta">
                                بتبلوك:
                                @foreach ($ticket->outgoingLinks->where('type', \App\Enums\LinkType::Blocks) as $link)
                                    @if ($link->toTicket && $link->toTicket->status->isOpen())
                                        <span class="u-mono u-ltr">{{ $link->toTicket->ticket_number }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <span class="blocked-flag">مبلوكة بيك</span>
                    </a>
                @empty
                    <p class="card__empty">مش مأخّر حد.</p>
                @endforelse
            </x-today-section>
        </div>
    </div>
@endsection
