@php
    /*
     * F17: a rating box only exists for a role that actually has someone on it.
     * Role-based since the fixed columns were dropped (2026-07-24): the list is
     * built from the ticket's role assignments, one box per assigned person.
     */
    $rows = $ticket->roleAssignments
        ->filter(fn ($assignment) => $assignment->user_id !== null && $assignment->user !== null)
        ->map(fn ($assignment) => [
            'role' => $assignment->role,
            'person' => $assignment->user,
            'existing' => $ratings->first(fn ($r) => $r->role_id === $assignment->role_id && $r->ratee_id === $assignment->user_id),
        ])
        ->values();

    /*
     * ★ (2026-08-04) Whether this card has anything to SAY, not just whether
     * the ticket has assignees.
     *
     * The @if below used to ask only "are there assignees", and the permission
     * checks lived inside the card. Someone with neither ratings permission —
     * a support agent, most of the team — got the card, its title and its
     * chevron wrapped around nothing at all. That was survivable while every
     * aside card opened folded; now that the action cards open by default it
     * is an empty box sitting open on the page.
     */
    $canGive = auth()->user()->hasPermission('ratings.give');
    $canSeeAll = auth()->user()->hasPermission('ratings.view.all');

    $visibleRows = match (true) {
        $canGive => $rows,
        // Read-only: only a rating that has actually been given is worth a row.
        $canSeeAll => $rows->filter(fn ($row) => $row['existing'] !== null)->values(),
        default => collect(),
    };
@endphp

@if ($visibleRows->isNotEmpty())
    <x-collapsible-card title="التقييمات" name="ratings" :open="true">
        <div class="stack stack--tight">
            @can('ratings.give')
                <p class="field__hint">اختياري — مش بيعطّل إغلاق التذكرة.</p>

                @foreach ($visibleRows as $row)
                    <form method="POST" action="{{ route('tickets.ratings.store', $ticket) }}" class="rating">
                        @csrf
                        <input type="hidden" name="role_id" value="{{ $row['role']->id }}">

                        <div class="rating__who">
                            <x-avatar :user="$row['person']" size="sm" />
                            <span>
                                {{ $row['person']?->name ?? '—' }}
                                <small class="u-subtle">{{ $row['role']?->name_ar }}</small>
                            </span>
                        </div>

                        <select name="score" class="select rating__score">
                            <option value="">—</option>
                            @for ($i = 1; $i <= 10; $i++)
                                <option value="{{ $i }}" @selected($row['existing']?->score === $i)>{{ $i }}</option>
                            @endfor
                        </select>

                        <input type="text" name="comment" class="input rating__comment"
                               placeholder="تعليق (اختياري)" value="{{ $row['existing']?->comment }}">

                        <x-button variant="ghost" size="sm">حفظ</x-button>
                    </form>
                @endforeach
            @else
                {{-- Without ratings.give you can still see them if you may see
                     everyone's. F17 --}}
                @can('ratings.view.all')
                    @foreach ($visibleRows as $row)
                        <div class="row row--between">
                            <span class="row">
                                <x-avatar :user="$row['person']" size="sm" />
                                {{ $row['person']?->name }}
                                <span class="u-subtle">{{ $row['role']?->name_ar }}</span>
                            </span>
                            <x-badge variant="neutral">{{ $row['existing']->score }}/10</x-badge>
                        </div>
                    @endforeach
                @endcan
            @endcan
        </div>
    </x-collapsible-card>
@endif
