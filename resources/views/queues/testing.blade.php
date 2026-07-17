@extends('layouts.app')

@section('title', 'طابور التيست')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">طابور التيست</h1>
                <p class="page-subtitle">التذاكر الي خلص تطويرها ومستنياك تراجعها.</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        <x-card title="مسندة ليك" flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th></th>
                            <th>التذكرة</th>
                            <th>المبرمجين</th>
                            <th>الحالة</th>
                            <th>العمر</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr class="tickets__row">
                                <td class="tickets__cell--stripe">
                                    <x-priority-stripe :priority="$ticket->priority" />
                                </td>
                                <td>
                                    <span class="tickets__number">{{ $ticket->ticket_number }}</span>
                                    <a class="tickets__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                                    <span class="tickets__company">{{ $ticket->company->name }}</span>
                                </td>
                                <td>
                                    <div class="tickets__people">
                                        @if ($ticket->frontend)<x-avatar :user="$ticket->frontend" size="sm" />@endif
                                        @if ($ticket->backend)<x-avatar :user="$ticket->backend" size="sm" />@endif
                                    </div>
                                </td>
                                <td><x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge></td>
                                <td @class(['tickets__age', 'tickets__age--overdue' => $ticket->isOverdue()])>
                                    {{ $ticket->ageLabel() }}
                                </td>
                                <td class="table__cell--actions">
                                    <x-button variant="secondary" size="sm" :href="route('tickets.show', $ticket)">راجع</x-button>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="6">مفيش حاجة مستنياك.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

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
                                        <span class="tickets__company">{{ $ticket->company->name }}</span>
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
