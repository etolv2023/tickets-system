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
                    <x-badge :variant="$ticket->type->variant()">{{ $ticket->type->label() }}</x-badge>
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

        <div class="ticket">
            <div class="stack">
                @include('tickets.partials._description')
                @include('tickets.partials._subtasks')
                @include('tickets.partials._timeline')
            </div>

            <aside class="ticket__side">
                @include('tickets.partials._workflow')
                @include('tickets.partials._facts')
                @if (in_array($ticket->status->value, ['resolved', 'closed'], true))
                    @include('tickets.partials._ratings')
                @endif
                @include('tickets.partials._time')
                @include('tickets.partials._links')
                @include('tickets.partials._labels')
                @can('assign', $ticket)
                    @include('tickets.partials._assign')
                @endcan
            </aside>
        </div>
    </div>
@endsection
