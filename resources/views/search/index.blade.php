@extends('layouts.app')

@section('title', 'البحث')

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">البحث</h1>
                <p class="page-subtitle">التذاكر والصب تاسكس والتعليقات والشركات.</p>
            </div>
        </div>

        <form method="GET" action="{{ route('search') }}" class="filters">
            <input
                type="search"
                name="q"
                value="{{ $term }}"
                placeholder="ابحث… أو الصق رقم تذكرة زي TK-2026-00001"
                class="input filters__search"
                autofocus
            >
            <x-button variant="primary">ابحث</x-button>
        </form>

        @if ($tooShort)
            <x-alert>
                اكتب 3 حروف على الأقل — الفهرس بتاع MySQL بيتجاهل الكلمات الأقصر من كده.
            </x-alert>
        @endif

        @if ($results)
            @php $total = collect($results)->sum(fn ($r) => $r->count()); @endphp

            @if ($total === 0)
                <x-card>
                    <p class="card__empty">مفيش نتايج لـ «{{ $term }}».</p>
                </x-card>
            @endif

            @if ($results['tickets']->isNotEmpty())
                <x-card title="التذاكر" flush>
                    <x-slot:actions><span class="u-subtle">{{ $results['tickets']->count() }}</span></x-slot:actions>
                    <div class="subtasks">
                        @foreach ($results['tickets'] as $ticket)
                            <div class="today__row">
                                <x-priority-stripe :priority="$ticket->priority" />
                                <div class="today__row-main">
                                    <a class="today__row-title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                                    <div class="today__row-meta">
                                        <span class="u-mono u-ltr">{{ $ticket->ticket_number }}</span>
                                        · {{ $ticket->company->name }}
                                    </div>
                                </div>
                                <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if ($results['subtasks']->isNotEmpty())
                <x-card title="الصب تاسكس" flush>
                    <x-slot:actions><span class="u-subtle">{{ $results['subtasks']->count() }}</span></x-slot:actions>
                    <div class="subtasks">
                        @foreach ($results['subtasks'] as $subtask)
                            <div class="today__row">
                                <div class="today__row-main">
                                    <a class="today__row-title" href="{{ route('tickets.show', $subtask->ticket_id) }}">
                                        {{ $subtask->title }}
                                    </a>
                                    <div class="today__row-meta">
                                        <span class="u-mono u-ltr">{{ $subtask->ticket->ticket_number }}</span>
                                        · {{ $subtask->side->label() }}
                                    </div>
                                </div>
                                <x-badge :variant="$subtask->status->variant()">{{ $subtask->status->label() }}</x-badge>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if ($results['comments']->isNotEmpty())
                <x-card title="التعليقات" flush>
                    <x-slot:actions><span class="u-subtle">{{ $results['comments']->count() }}</span></x-slot:actions>
                    <div class="subtasks">
                        @foreach ($results['comments'] as $comment)
                            <div class="today__row">
                                <div class="today__row-main">
                                    <a class="today__row-title" href="{{ route('tickets.show', [$comment->ticket_id, '#comment-' . $comment->id]) }}">
                                        {{ Str::limit(strip_tags($comment->body), 70) }}
                                    </a>
                                    <div class="today__row-meta">
                                        {{ $comment->user->name }}
                                        · <span class="u-mono u-ltr">{{ $comment->ticket->ticket_number }}</span>
                                        · {{ $comment->created_at->diffForHumans() }}
                                    </div>
                                </div>
                                @if ($comment->is_internal)
                                    <x-badge variant="medium">داخلي</x-badge>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif

            @if ($results['companies']->isNotEmpty())
                <x-card title="الشركات" flush>
                    <div class="subtasks">
                        @foreach ($results['companies'] as $company)
                            <div class="today__row">
                                <div class="today__row-main">
                                    <a class="today__row-title" href="{{ route('admin.companies.show', $company) }}">
                                        {{ $company->name }}
                                    </a>
                                    <div class="today__row-meta u-mono u-ltr">{{ $company->code }}</div>
                                </div>
                                @unless ($company->is_active)
                                    <x-badge variant="neutral">موقوفة</x-badge>
                                @endunless
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endif
        @endif
    </div>
@endsection
