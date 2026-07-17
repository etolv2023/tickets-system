@extends('layouts.app')

@section('title', 'طابور الموافقات')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">مستني قرارك</h1>
                <p class="page-subtitle">فيتشرات وموديولات متتوزعش قبل ما توافق عليها.</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th></th>
                            <th>التذكرة</th>
                            <th>النوع</th>
                            <th>طلبها</th>
                            <th>مستنية من</th>
                            <th class="table__cell--actions">القرار</th>
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
                                <td><x-badge :variant="$ticket->type->variant()">{{ $ticket->type->label() }}</x-badge></td>
                                <td>{{ $ticket->creator->name }}</td>
                                <td class="tickets__age">{{ $ticket->ageLabel() }}</td>
                                <td class="table__cell--actions">
                                    <div class="row">
                                        <form method="POST" action="{{ route('tickets.approve', $ticket) }}">
                                            @csrf
                                            <x-button variant="primary" size="sm">موافقة</x-button>
                                        </form>
                                        <x-button variant="ghost" size="sm" :href="route('tickets.show', $ticket)">
                                            افتحها للرفض
                                        </x-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="6">مفيش حاجة مستنية قرارك.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $tickets->links() }}
    </div>
@endsection
