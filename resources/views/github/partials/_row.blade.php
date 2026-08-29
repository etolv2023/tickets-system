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

    {{-- ★ (2026-08-29) The point of the screen, and it is NOT a count.

         A ticket with backend and frontend work owes a branch in each. One of
         the two is not "half covered", it is wrong — and a number in a column
         says «1» for both the finished ticket and the broken one. So: a chip
         per side, and the missing one is red and says «ناقص».

         All derived, zero queries — see App\Support\BranchCoverage. --}}
    <td>
        @php $coverage = \App\Support\BranchCoverage::for($ticket); @endphp

        @if ($coverage === [])
            <span class="u-subtle">—</span>
        @else
            <div class="row row--wrap gh-cover">
                @foreach ($coverage as $side)
                    @if ($side['covered'])
                        <x-badge variant="green">{{ $side['label'] }} ✔</x-badge>
                    @else
                        <x-badge variant="red">{{ $side['label'] }} ناقص</x-badge>
                    @endif
                @endforeach
            </div>
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
