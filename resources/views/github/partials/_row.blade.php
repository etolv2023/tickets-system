{{-- One ticket with no branch behind it.

     Same columns and same glyph treatment as the ticket list, so a row reads
     identically on both screens — the type gets the tinted icon rather than a
     third pill, exactly as tickets/index does and for the same reason. --}}

<tr>
    <td>
        <a href="{{ route('tickets.show', $ticket) }}" class="gh-miss__ticket">
            <span class="u-mono u-ltr">{{ $ticket->ticket_number }}</span>
            <span class="gh-miss__title">{{ Str::limit($ticket->title, 55) }}</span>
        </a>
    </td>

    <td class="table__cell--muted">{{ $ticket->company?->name ?? 'داخلية' }}</td>

    <td class="tickets__type">
        <x-icon :name="$ticket->type->icon()" class="tickets__type-icon"
                style="--type-color: var(--c-{{ $ticket->type->variant() }}, var(--text-subtle))" />
        {{ $ticket->type->label() }}
    </td>

    <td class="table__cell--tight">
        <x-badge :variant="$ticket->priority->variant()"
                 :icon="$ticket->priority->icon()">{{ $ticket->priority->label() }}</x-badge>
    </td>

    <td class="table__cell--tight">
        <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
    </td>

    {{-- ★ (2026-08-29) Needed once the screen can show tickets that DO have a
         branch: without it, "ليها برانش" is a list you have to take on trust.
         The total across every repository, even when the filter names one. --}}
    <td class="table__cell--num u-mono">
        @if ($ticket->branches_count > 0)
            {{ $ticket->branches_count }}
        @else
            <span class="u-subtle">—</span>
        @endif
    </td>

    <td class="u-mono table__cell--muted">
        {{ $ticket->resolved_at?->timezone(config('app.display_timezone'))->translatedFormat('j M Y') ?? '—' }}
    </td>

    <td>
        <div class="gh-miss__people">
            @forelse ($ticket->roleAssignments as $assignment)
                @if ($assignment->user)
                    <x-avatar :user="$assignment->user" size="sm" />
                @endif
            @empty
                <span class="u-subtle">مش موزعة</span>
            @endforelse
        </div>
    </td>
</tr>
