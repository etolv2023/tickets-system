@extends('layouts.app')

@section('title', 'التذاكر')

@section('content')
    <div class="page page--wide">
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- A status change can be refused from here now (an unfinished
             subtask, a client not yet notified), so the refusal has to have
             somewhere to land. --}}
        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        {{-- No page heading above the filters: the nav already says where you
             are, and the design puts the "new ticket" action inside the bar. --}}
        @include('tickets.partials._filters')

        {{-- Genuinely empty — day one, no ticket opened yet — is a different
             screen from "no results for this filter" and gets the one
             message worth spending words on (blank.css). --}}
        @if ($tickets->total() === 0 && ! array_filter($filters))
            <x-card>
                <div class="blank">
                    <p class="blank__title">مفيش تذاكر لسه.</p>
                    <p class="blank__body">
                        أول ما تفتح تذكرة هتلاقيها هنا — بترتيب الأولوية، الأقدم أولاً.
                    </p>
                    @can('create', App\Models\Ticket::class)
                        <div class="blank__actions">
                            <x-button variant="primary" :href="route('tickets.create')">
                                <x-icon name="plus" class="btn__icon" />
                                تذكرة جديدة
                            </x-button>
                        </div>
                    @endcan
                </div>
            </x-card>
        @else
            <x-card flush>
                <div class="table-wrap">
                    <table class="table table--hover table--zebra">
                        <thead>
                            <tr>
                                <th>التذكرة</th>
                                <th>الأولوية</th>
                                <th>الحالة</th>
                                <th>المسؤولين</th>
                                <th>أنشأها</th>
                                <th>{{ \App\Models\Ticket::DATE_BASES[$dateBasis] }}</th>
                                <th>الموعد والعمر</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tickets as $ticket)
                                @include('tickets.partials._row', ['ticket' => $ticket])
                            @empty
                                <tr class="table__empty">
                                    <td colspan="7">مفيش تذاكر بالفلاتر دي.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- The count reads as a footnote under the table, not as a heading
                 above it — you look at it after scanning, if at all. --}}
            <span class="tickets__count">
                {{ $tickets->total() }} تذكرة · الترتيب: {{ \App\Models\Ticket::SORTS[$filters['sort'] ?? 'default'] ?? \App\Models\Ticket::SORTS['default'] }}.
            </span>

            {{ $tickets->links() }}
        @endif
    </div>
@endsection
