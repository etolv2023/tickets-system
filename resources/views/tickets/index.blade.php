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

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>الرقم</th>
                            <th>العنوان</th>
                            <th>الشركة</th>
                            <th>النوع</th>
                            <th>الأولوية</th>
                            <th>الحالة</th>
                            <th>المسؤولين</th>
                            <th>{{ ($filters['status'] ?? '') === 'resolved' ? 'زمن الحل' : 'SLA' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tickets as $ticket)
                            <tr class="tickets__row">
                                <td class="table__cell--tight">
                                    <span class="tickets__number">{{ $ticket->ticket_number }}</span>
                                </td>
                                <td>
                                    <a class="tickets__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
                                </td>
                                <td class="tickets__cell--company">{{ $ticket->originLabel() }}</td>
                                {{-- Type stays un-badged: four pills in one row and none of
                                     them reads. It gets the glyph instead, tinted in the
                                     type's own hue — the type is legible at a glance now
                                     without adding a third competing pill shape. --}}
                                <td class="tickets__type">
                                    <x-icon :name="$ticket->type->icon()" class="tickets__type-icon"
                                            {{-- The fallback catches "neutral", which is a variant
                                                 name but deliberately not a hue token. --}}
                                            style="--type-color: var(--c-{{ $ticket->type->variant() }}, var(--text-subtle))" />
                                    {{ $ticket->type->label() }}
                                </td>
                                <td class="table__cell--tight">
                                    <x-badge :variant="$ticket->priority->variant()" :icon="$ticket->priority->icon()">{{ $ticket->priority->label() }}</x-badge>
                                </td>
                                {{-- Editable in place: the common move is one
                                     click from the list, without opening the
                                     ticket. Falls back to a badge for anyone
                                     who can't change it. --}}
                                <td class="table__cell--tight">
                                    <x-status-select :ticket="$ticket" />
                                </td>
                                <td>
                                    <div class="tickets__people">
                                        @if ($ticket->frontend)
                                            <x-avatar :user="$ticket->frontend" size="sm" />
                                        @endif
                                        @if ($ticket->backend)
                                            <x-avatar :user="$ticket->backend" size="sm" />
                                        @endif
                                        @if ($ticket->devops)
                                            <x-avatar :user="$ticket->devops" size="sm" />
                                        @endif
                                        @unless ($ticket->frontend || $ticket->backend || $ticket->devops)
                                            <span class="u-subtle">مش موزعة</span>
                                        @endunless
                                    </div>
                                </td>
                                {{-- Red is the whole message here; the age stays
                                     beside it so nothing is lost to the colour. --}}
                                <td class="table__cell--tight">
                                    @if ($ticket->isOverdue())
                                        <span class="tickets__age tickets__age--overdue">تخطّى</span>
                                        <span class="tickets__age u-subtle">{{ $ticket->ageLabel() }}</span>
                                    @else
                                        <span class="tickets__age">{{ $ticket->ageLabel() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="8">
                                    {{ array_filter($filters) ? 'مفيش تذاكر بالفلاتر دي.' : 'مفيش تذاكر لسه.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{-- The count reads as a footnote under the table, not as a heading
             above it — you look at it after scanning, if at all. --}}
        <span class="tickets__count">
            {{ $tickets->total() }} تذكرة · مرتبة بالأولوية ثم الأقدم أولاً.
        </span>

        {{ $tickets->links() }}
    </div>
@endsection
