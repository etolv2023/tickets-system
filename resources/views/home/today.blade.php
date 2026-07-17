@extends('layouts.app')

@section('title', 'اليوم')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="today__greeting">أهلاً، {{ $user->name }}</h1>
                <p class="today__date">
                    {{ now(config('app.display_timezone'))->translatedFormat('l j F Y') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- The four questions from F22.1, in the order they matter. No totals
             row, no charts — the numbers live at /reports and you go to them
             on purpose (CLAUDE.md § 6). --}}
        <div class="today">
            <x-today-section question="الي على دماغي" hint="صب تاسكس مستحقة النهاردة أو متأخرة">
                @forelse ($onMyPlate as $subtask)
                    <div class="today__row">
                        <x-priority-stripe :priority="$subtask->ticket->priority" />
                        <div class="today__row-main">
                            <a class="today__row-title" href="{{ route('tickets.show', $subtask->ticket_id) }}">
                                {{ $subtask->title }}
                            </a>
                            <div class="today__row-meta">
                                <span class="u-mono u-ltr">{{ $subtask->ticket->ticket_number }}</span>
                                · {{ $subtask->ticket->company->name }}
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
                    </div>
                @empty
                    <p class="card__empty">مفيش حاجة مستحقة عليك.</p>
                @endforelse
            </x-today-section>

            <x-today-section question="الي بيولّع" hint="تذاكر خرقت مهلة الـ SLA">
                @forelse ($onFire as $ticket)
                    <div class="today__row">
                        <x-priority-stripe :priority="$ticket->priority" />
                        <div class="today__row-main">
                            <a class="today__row-title" href="{{ route('tickets.show', $ticket) }}">
                                {{ $ticket->title }}
                            </a>
                            <div class="today__row-meta">
                                <span class="u-mono u-ltr">{{ $ticket->ticket_number }}</span>
                                · {{ $ticket->company->name }}
                            </div>
                        </div>
                        <span class="tickets__age tickets__age--overdue">
                            عدّى بـ {{ $ticket->sla_due_at->diffForHumans(null, true) }}
                        </span>
                    </div>
                @empty
                    <p class="card__empty">مفيش خرق للمهلة.</p>
                @endforelse
            </x-today-section>

            @can('features.approve')
                <x-today-section question="مستني قراري" hint="فيتشرات وموديولات مستنية موافقتك">
                    @forelse ($awaitingMe as $ticket)
                        <div class="today__row">
                            <x-priority-stripe :priority="$ticket->priority" />
                            <div class="today__row-main">
                                <a class="today__row-title" href="{{ route('tickets.show', $ticket) }}">
                                    {{ $ticket->title }}
                                </a>
                                <div class="today__row-meta">
                                    <span class="u-mono u-ltr">{{ $ticket->ticket_number }}</span>
                                    · {{ $ticket->company->name }}
                                </div>
                            </div>
                            <x-badge :variant="$ticket->type->variant()">{{ $ticket->type->label() }}</x-badge>
                        </div>
                    @empty
                        <p class="card__empty">مفيش حاجة مستنية قرارك.</p>
                    @endforelse
                </x-today-section>
            @endcan

            <x-today-section question="أنا مأخّر مين" hint="تذاكر شغلك بيبلوكها">
                @forelse ($blockingOthers as $ticket)
                    <div class="today__row">
                        <x-priority-stripe :priority="$ticket->priority" />
                        <div class="today__row-main">
                            <a class="today__row-title" href="{{ route('tickets.show', $ticket) }}">
                                {{ $ticket->title }}
                            </a>
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
                    </div>
                @empty
                    <p class="card__empty">مش مأخّر حد.</p>
                @endforelse
            </x-today-section>
        </div>
    </div>
@endsection
