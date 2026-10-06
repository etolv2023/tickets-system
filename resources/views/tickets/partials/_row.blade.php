{{-- One row of /tickets. Split out of index.blade.php (CLAUDE.md § 3: a
     Blade file past 150 lines gets divided) when the deadline column joined
     the table. Everything here was already selected or eager-loaded by
     TicketController::index — no query runs inside this file. --}}
<tr class="tickets__row">
    <td class="table__cell--tight">
        <span class="tickets__number">{{ $ticket->ticket_number }}</span>

        {{-- ★ (2026-08-29) F27. In the normal list this warning is reserved
             for finished tickets, because an open ticket with no branch yet
             is ordinary. In the explicit "من غير برانش" view every result
             must carry the marker; otherwise identical zero-count rows look
             as if the filter disagrees with itself. --}}
        @can('github.view')
            @if ($ticket->branches_count === 0 && (
                ($filters['branch'] ?? null) === 'none'
                || in_array($ticket->status->value, \App\Models\TicketStatusDefinition::resolvedKeys(), true)
            ))
                <x-badge variant="amber" class="badge--sm">ملهاش برانش</x-badge>
            @endif
        @endcan
    </td>
    <td>
        <a class="tickets__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>
        {{-- F11 labels — data existed but the list never showed them,
             so "does this need my attention" meant opening the ticket
             to find out. Small and quiet: the title still leads.

             ★ (2026-08-29) The subtask counter joined this row rather
             than taking a tenth column: the table already scrolls
             sideways at this width, and «كام خطوة خلصت» belongs beside
             the ticket it describes, not five columns away from it.
             Both counts were already selected — no new query. --}}
        @if ($ticket->labels->isNotEmpty() || $ticket->subtasks_total > 0)
            <div class="row row--wrap tickets__labels">
                @if ($ticket->subtasks_total > 0)
                    {{-- Hidden at zero, like the board card: a ticket with
                         no subtasks has nothing to report, and "0/0" on
                         every other row is noise that teaches you to stop
                         reading the column. --}}
                    <span class="tickets__subtasks"
                          title="الصب تاسكس المكتملة من الإجمالي">
                        <x-icon name="list-checks" size="0.9em" />
                        {{ $ticket->subtasks_done }}/{{ $ticket->subtasks_total }}
                    </span>
                @endif

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
    <td class="table__cell--muted u-nums">{{ $ticket->dateBasisLabel($dateBasis) }}</td>
    <td class="table__cell--tight tickets__deadline">
        <x-badge :variant="$ticket->deadlineStatus()['key']" class="badge--sm tickets__deadline-badge">
            {{ $ticket->deadlineStatus()['label'] }}
        </x-badge>
        <span class="tickets__deadline-delta">{{ $ticket->deadlineStatus()['delta'] }}</span>
        <span class="tickets__age u-subtle">{{ $ticket->ageLabel() }}</span>
    </td>
</tr>
