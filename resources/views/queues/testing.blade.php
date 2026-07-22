@extends('layouts.app')

@section('title', 'طابور التيست')

@section('content')
    {{-- ★★★★ (2026-07-21): a ticket table, same case as /tickets. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">طابور التيست</h1>
                <p class="page-subtitle">تذاكر خلصت تطوير ومستنية مراجعتك — أكّد الحل أو رجّعها بملاحظة.</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        <div class="queue">
            @forelse ($tickets as $ticket)
                <article class="queue-card" x-data="{ returning: false }">
                    <x-priority-stripe :priority="$ticket->priority" />

                    <div class="queue-card__badges">
                        <span class="queue-card__number">{{ $ticket->ticket_number }}</span>
                        <x-badge :variant="$ticket->status->variant()" class="badge--sm">{{ $ticket->status->label() }}</x-badge>
                    </div>

                    <a class="queue-card__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>

                    <div class="queue-card__meta">
                        <span>{{ $ticket->originLabel() }}</span>
                        {{-- The developers to talk to — whoever logged work on it
                             (role-based since 2026-07-24). --}}
                        @foreach ($ticket->workLogs as $log)
                            @if ($log->user)
                                <span aria-hidden="true">·</span>
                                <x-avatar :user="$log->user" size="sm" />
                                <span>{{ $log->user->name }}</span>
                            @endif
                        @endforeach
                        <span aria-hidden="true">·</span>
                        <span @class(['queue-card__age--overdue' => $ticket->isOverdue()])>{{ $ticket->ageLabel() }}</span>
                    </div>

                    <div class="queue-card__actions">
                        @if (auth()->user()->hasPermission('tickets.resolve'))
                            <form method="POST" action="{{ route('tickets.verify', $ticket) }}">
                                @csrf
                                <x-button variant="confirm">
                                    <x-icon name="check" class="btn__icon" />
                                    أكّد الحل
                                </x-button>
                            </form>
                        @endif

                        @if (auth()->user()->hasPermission('tickets.reopen'))
                            <x-button variant="secondary" type="button" x-on:click="returning = ! returning"
                                      x-bind:aria-expanded="returning ? 'true' : 'false'">
                                رجّع بملاحظة
                            </x-button>
                        @endif

                        <a class="btn btn--ghost queue-card__open" href="{{ route('tickets.show', $ticket) }}">
                            فتح التذكرة
                            <x-icon name="chevron-left" class="btn__icon" />
                        </a>
                    </div>

                    @if (auth()->user()->hasPermission('tickets.reopen'))
                        <form class="queue-card__reason" method="POST" action="{{ route('tickets.reopen', $ticket) }}"
                              x-show="returning" x-cloak>
                            @csrf
                            <label class="u-label" for="reopen-reason-{{ $ticket->id }}">سبب الارتجاع</label>
                            <textarea id="reopen-reason-{{ $ticket->id }}" name="reason" class="textarea" rows="2"
                                      maxlength="500" placeholder="المبرمج محتاج يعرف يصلح إيه بالظبط…"></textarea>
                            <div class="queue-card__reason-actions">
                                <x-button variant="primary" size="sm">رجّعها</x-button>
                                <x-button variant="ghost" size="sm" type="button" x-on:click="returning = false">إلغاء</x-button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <p class="queue__empty">مفيش حاجة مستنياك.</p>
            @endforelse
        </div>

        {{ $tickets->links() }}

        {{-- F16: dev_done with nobody assigned to test it. Support or a manager
             has to pick these up, or they sit forever. --}}
        @if ($unassigned->isNotEmpty())
            <x-card title="خلص تطويرها ومفيش تيستر" flush>
                <x-slot:actions>
                    <span class="u-subtle">محتاجة حد يحلها أو يسند لها تيستر</span>
                </x-slot:actions>

                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th></th>
                                <th>التذكرة</th>
                                <th>العمر</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($unassigned as $ticket)
                                <tr class="tickets__row">
                                    <td class="tickets__cell--stripe">
                                        <x-priority-stripe :priority="$ticket->priority" />
                                    </td>
                                    <td>
                                        <span class="tickets__number">{{ $ticket->ticket_number }}</span>
                                        <a class="tickets__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                                        <span class="tickets__company">{{ $ticket->originLabel() }}</span>
                                    </td>
                                    <td @class(['tickets__age', 'tickets__age--overdue' => $ticket->isOverdue()])>
                                        {{ $ticket->ageLabel() }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    </div>
@endsection
