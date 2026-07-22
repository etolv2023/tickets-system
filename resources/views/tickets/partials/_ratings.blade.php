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
@endphp

@if ($rows->isNotEmpty())
    <x-collapsible-card title="التقييمات">
        <div class="stack stack--tight">
            @can('ratings.give')
                <p class="field__hint">اختياري — مش بيعطّل إغلاق التذكرة.</p>

                @foreach ($rows as $row)
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
                    @foreach ($rows->filter(fn ($r) => $r['existing']) as $row)
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
