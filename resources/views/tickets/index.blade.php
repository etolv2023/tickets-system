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
                                <th>الرقم</th>
                                <th>العنوان</th>
                                <th>الشركة</th>
                                <th>النوع</th>
                                <th>الأولوية</th>
                                <th>الحالة</th>
                                <th>المسؤولين</th>
                                <th>أنشأها</th>
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
                                        {{-- F11 labels — data existed but the list never showed them,
                                             so "does this need my attention" meant opening the ticket
                                             to find out. Small and quiet: the title still leads. --}}
                                        @if ($ticket->labels->isNotEmpty())
                                            <div class="row row--wrap tickets__labels">
                                                @foreach ($ticket->labels as $label)
                                                    <x-badge :variant="$label->color" class="badge--sm">{{ $label->name }}</x-badge>
                                                @endforeach
                                            </div>
                                        @endif
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
                                        {{-- Role-based assignment (2026-07-24):
                                             one avatar per assigned person. --}}
                                        <div class="tickets__people">
                                            @forelse ($ticket->roleAssignments as $assignment)
                                                @if ($assignment->user)
                                                    <x-avatar :user="$assignment->user" size="sm" />
                                                @endif
                                            @empty
                                                <span class="u-subtle">مش موزعة</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="table__cell--muted">{{ $ticket->creator?->name ?? '—' }}</td>
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
                                    <td colspan="9">مفيش تذاكر بالفلاتر دي.</td>
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
        @endif
    </div>
@endsection
