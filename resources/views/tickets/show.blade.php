@extends('layouts.app')

@section('title', $ticket->ticket_number)

@push('scripts')
    {{-- Only this screen reorders subtasks, so only this screen loads Sortable. --}}
    @vite('resources/js/features/subtasks.js')
@endpush

@section('content')
    <div class="page">
        @include('tickets.partials._header')

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
