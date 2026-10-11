@extends('layouts.app')

@section('title', __('جاهزة للإغلاق'))

@section('content')
    {{-- A table, not the queue cards the other two use: this list is scanned,
         not acted on one card at a time, and the ask was "زي صفحة التذاكر". --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('جاهزة للإغلاق') }}</h1>
                <p class="page-subtitle">
                    {{ __('تذاكر') }} <strong>{{ __('اكتملت جميع مهامها الفرعية') }}</strong> {{ __('بينما ما زالت مفتوحة — العمل منتهٍ ولم يحرّك أحد التذكرة.') }}
                    <br>
                    {{ __('لا تظهر في أي شاشة أخرى: في قائمة التذاكر يبدو صفها كأي تذكرة مفتوحة، وكذلك في لوحة المهام.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        @include('queues.partials._ready-filters')

        <x-card flush>
            @if ($tickets->isEmpty())
                <div class="blank">
                    <p class="blank__title">{{ __('لا توجد تذكرة جاهزة للإغلاق.') }}</p>
                    <p class="blank__body">
                        {{ __('كل تذكرة اكتملت مهامها الفرعية أُغلقت فعلًا — أو ما زال فيها عمل غير مكتمل.') }}
                    </p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>{{ __('الرقم') }}</th>
                                <th>{{ __('العنوان') }}</th>
                                <th>{{ __('الشركة') }}</th>
                                <th>{{ __('النوع') }}</th>
                                <th>{{ __('الأولوية') }}</th>
                                <th>{{ __('الحالة') }}</th>
                                <th class="table__cell--num">{{ __('المهام الفرعية') }}</th>
                                <th>{{ __('المسؤولين') }}</th>
                                <th>SLA</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr class="tickets__row">
                                    <td class="table__cell--tight">
                                        <span class="tickets__number">{{ $ticket->ticket_number }}</span>
                                    </td>

                                    <td>
                                        <a class="tickets__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                                    </td>

                                    <td class="tickets__cell--company">{{ $ticket->originLabel() }}</td>

                                    <td class="tickets__type">
                                        <x-icon :name="$ticket->type->icon()" class="tickets__type-icon"
                                                style="--type-color: var(--c-{{ $ticket->type->variant() }}, var(--text-subtle))" />
                                        {{ $ticket->type->label() }}
                                    </td>

                                    <td class="table__cell--tight">
                                        <x-badge :variant="$ticket->priority->variant()"
                                                 :icon="$ticket->priority->icon()">{{ $ticket->priority->label() }}</x-badge>
                                    </td>

                                    {{-- Editable in place, same as /tickets: the whole point of
                                         this screen is that the status needs moving, so the
                                         control that moves it belongs on the row. --}}
                                    <td class="table__cell--tight">
                                        <x-status-select :ticket="$ticket" />
                                    </td>

                                    {{-- Always n/n here by definition — shown anyway so the row
                                         carries its own evidence rather than asking for trust. --}}
                                    <td class="table__cell--num">
                                        <span class="tickets__subtasks">
                                            <x-icon name="list-checks" size="0.9em" />
                                            {{ $ticket->subtasks_done }}/{{ $ticket->subtasks_total }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="tickets__people">
                                            @forelse ($ticket->roleAssignments as $assignment)
                                                @if ($assignment->user)
                                                    <x-avatar :user="$assignment->user" size="sm" />
                                                @endif
                                            @empty
                                                <span class="u-subtle">{{ __('غير مُسندة') }}</span>
                                            @endforelse
                                        </div>
                                    </td>

                                    <td class="table__cell--tight">
                                        @if ($ticket->isOverdue())
                                            <span class="tickets__age tickets__age--overdue">{{ __('تخطٍّ') }}</span>
                                        @endif
                                        <span class="tickets__age">{{ $ticket->ageLabel() }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <span class="tickets__count">
            {{ trans_choice(':count تذكرة|تذكرة واحدة|تذكرتان|:count تذاكر|:count تذكرة|:count تذكرة', $tickets->total()) }} · {{ __('مرتبة حسب الأولوية ثم الأقدم.') }}
        </span>

        {{ $tickets->links() }}
    </div>
@endsection
