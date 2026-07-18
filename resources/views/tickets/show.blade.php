@extends('layouts.app')

@section('title', $ticket->ticket_number)

@push('scripts')
    {{-- Only this screen reorders subtasks, so only this screen loads Sortable. --}}
    @vite('resources/js/features/board.js')
@endpush

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <span class="ticket__number">{{ $ticket->ticket_number }}</span>
                <h1 class="ticket__title">{{ $ticket->title }}</h1>
                <div class="ticket__badges">
                    <x-badge :variant="$ticket->type->variant()" :icon="$ticket->type->icon()">{{ $ticket->type->label() }}</x-badge>
                    <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
                    <x-badge variant="neutral">{{ $ticket->scope->label() }}</x-badge>
                    @if ($ticket->module)
                        <x-badge variant="neutral">{{ $ticket->module }}</x-badge>
                    @endif
                    @foreach ($ticket->labels as $label)
                        <x-badge :variant="$label->color">{{ $label->name }}</x-badge>
                    @endforeach
                    @if ($ticket->isBlocked())
                        <span class="blocked-flag">
                            <svg width="12" height="12" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM5.3 5.3a1 1 0 011.4 0l7 7a1 1 0 01-1.4 1.4l-7-7a1 1 0 010-1.4z" clip-rule="evenodd" />
                            </svg>
                            مبلوكة
                        </span>
                    @endif
                </div>
            </div>
            @can('update', $ticket)
                <div class="page__actions">
                    <x-button variant="ghost" :href="route('tickets.edit', $ticket)">تعديل</x-button>
                </div>
            @endcan
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        @php
            /*
             * Which tab opens first. A failed subtask or time-entry submit must
             * land on the panel that holds the error, otherwise the message is
             * rendered into a hidden div and the user sees nothing.
             */
            $linkCount = $ticket->outgoingLinks->count() + $ticket->incomingLinks->count();

            $initialTab = match (true) {
                $errors->hasAny(['side', 'estimated_hours', 'start_date', 'due_date', 'blocked_reason']) => 'subtasks',
                $errors->hasAny(['hours', 'worked_on', 'subtask_id']) => 'time',
                $errors->hasAny(['to_ticket_id', 'link_type']) => 'links',
                default => 'timeline',
            };
        @endphp

        <div class="ticket">
            <div class="stack">
                @include('tickets.partials._description')

                {{-- Four panels, one column. The ticket body stays the thing you
                     read; everything else waits behind a tab. --}}
                <div x-data="{ tab: @js($initialTab) }">
                    <div class="tabs" role="tablist">
                        <button type="button" role="tab" @click="tab = 'timeline'"
                                :class="tab === 'timeline' && 'tabs__tab--active'"
                                :aria-selected="tab === 'timeline' ? 'true' : 'false'"
                                class="tabs__tab">
                            الخط الزمني
                        </button>

                        <button type="button" role="tab" @click="tab = 'subtasks'"
                                :class="tab === 'subtasks' && 'tabs__tab--active'"
                                :aria-selected="tab === 'subtasks' ? 'true' : 'false'"
                                class="tabs__tab">
                            الصب تاسكس
                            @if ($ticket->subtasks_total > 0)
                                <span class="tabs__count">{{ $ticket->subtasks_total }}</span>
                            @endif
                        </button>

                        <button type="button" role="tab" @click="tab = 'time'"
                                :class="tab === 'time' && 'tabs__tab--active'"
                                :aria-selected="tab === 'time' ? 'true' : 'false'"
                                class="tabs__tab">
                            تسجيل الوقت
                        </button>

                        <button type="button" role="tab" @click="tab = 'links'"
                                :class="tab === 'links' && 'tabs__tab--active'"
                                :aria-selected="tab === 'links' ? 'true' : 'false'"
                                class="tabs__tab">
                            الروابط
                            @if ($linkCount > 0)
                                <span class="tabs__count">{{ $linkCount }}</span>
                            @endif
                        </button>
                    </div>

                    <div x-show="tab === 'timeline'" role="tabpanel">
                        @include('tickets.partials._timeline')
                    </div>

                    <div x-show="tab === 'subtasks'" x-cloak role="tabpanel">
                        @include('tickets.partials._subtasks')
                    </div>

                    <div x-show="tab === 'time'" x-cloak role="tabpanel">
                        @include('tickets.partials._time')
                    </div>

                    <div x-show="tab === 'links'" x-cloak role="tabpanel">
                        @include('tickets.partials._links')
                    </div>
                </div>
            </div>

            <aside class="ticket__side">
                @include('tickets.partials._workflow')
                @include('tickets.partials._facts')
                @if (in_array($ticket->status->value, ['resolved', 'closed'], true))
                    @include('tickets.partials._ratings')
                @endif
                @include('tickets.partials._labels')
                @can('assign', $ticket)
                    @include('tickets.partials._assign')
                @endcan
            </aside>
        </div>
    </div>
@endsection
