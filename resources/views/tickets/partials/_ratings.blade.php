@php
    /*
     * F17: a rating box only exists for a side that actually has someone on it.
     * "مفيش فرونت متعيّن → خانة الفرونت متظهرش أصلاً (مش disabled)" — so this
     * builds the list from who is really there, rather than rendering four and
     * greying some out.
     */
    $sides = collect(\App\Enums\PointSide::cases())
        ->map(fn ($side) => [
            'side' => $side,
            'userId' => $ticket->{$side->participantColumn()},
        ])
        ->filter(fn ($row) => $row['userId'] !== null)
        ->map(fn ($row) => $row + [
            'person' => $ticket->{match ($row['side']->value) {
                'support' => 'creator',
                'frontend' => 'frontend',
                'backend' => 'backend',
                default => 'tester',
            }},
            'existing' => $ratings->first(fn ($r) => $r->side === $row['side'] && $r->ratee_id === $row['userId']),
        ]);
@endphp

@if ($sides->isNotEmpty())
    <x-collapsible-card title="التقييمات">
        <div class="stack stack--tight">
            @can('ratings.give')
                <p class="field__hint">اختياري — مش بيعطّل إغلاق التذكرة.</p>

                @foreach ($sides as $row)
                    <form method="POST" action="{{ route('tickets.ratings.store', $ticket) }}" class="rating">
                        @csrf
                        <input type="hidden" name="side" value="{{ $row['side']->value }}">

                        <div class="rating__who">
                            <x-avatar :user="$row['person']" size="sm" />
                            <span>
                                {{ $row['person']?->name ?? '—' }}
                                <small class="u-subtle">{{ $row['side']->label() }}</small>
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
                    @foreach ($sides->filter(fn ($r) => $r['existing']) as $row)
                        <div class="row row--between">
                            <span class="row">
                                <x-avatar :user="$row['person']" size="sm" />
                                {{ $row['person']?->name }}
                                <span class="u-subtle">{{ $row['side']->label() }}</span>
                            </span>
                            <x-badge variant="neutral">{{ $row['existing']->score }}/10</x-badge>
                        </div>
                    @endforeach
                @endcan
            @endcan
        </div>
    </x-collapsible-card>
@endif
