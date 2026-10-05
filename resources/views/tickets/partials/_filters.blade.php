{{-- Every filter is a query-string key, so a filtered view is a shareable
     link — send your colleague exactly what you're looking at. F03.1

     ★ (2026-08-02) Was one flat flex-wrap row of ten controls with no grouping:
     the search box, six selects, two dates and three buttons all wrapped at
     whatever width the viewport happened to give them, so nothing sat where you
     last saw it. Now it reads as two deliberate lines — what you DO on top
     (search, my tickets, the primary action) and what you NARROW BY underneath
     — and the narrowing line is one grid, so the controls land in the same
     place every time.

     ★ (2026-10-05) The narrowing line moved to _filters-narrow / _filters-more
     when it grew a second tier; this file is the bar only. --}}
@php
    // "Mine" is on when the person filter is me across any relation — the same
    // state the button itself links to, so it can render as pressed.
    $mine = (int) ($filters['assignee'] ?? 0) === auth()->id()
        && in_array($filters['relation'] ?? 'any', ['any', ''], true);
    $narrowing = array_filter(array_diff_key($filters, ['q' => true]));
@endphp

<form method="GET" action="{{ route('tickets.index') }}" class="filters">
    <div class="filters__bar">
        <input
            type="search"
            name="q"
            value="{{ $filters['q'] ?? '' }}"
            placeholder="ابحث بالعنوان أو الوصف، أو الصق رقم تذكرة"
            class="input filters__search"
        >

        @if ($canSeeOthers)
            {{-- A link, not a checkbox: it is a saved view, and it has to be
                 shareable and back-buttonable like every other filter here. --}}
            <a href="{{ $mine ? route('tickets.index') : route('tickets.index', ['assignee' => auth()->id(), 'relation' => 'any']) }}"
               @class(['btn', 'btn--secondary', 'filters__mine', 'filters__mine--on' => $mine])
               @if ($mine) aria-pressed="true" @endif>
                <x-icon name="user" class="btn__icon" />
                تذاكري
            </a>
        @endif

        <x-button variant="secondary">فلترة</x-button>

        @if (array_filter($filters))
            <x-button variant="ghost" :href="route('tickets.index')">مسح</x-button>
        @endif

        <x-export-button route="export.tickets" />

        {{-- The one primary action on this screen lives at the end of the bar,
             not in a page header — the design's filter row carries it. --}}
        @can('create', App\Models\Ticket::class)
            <x-button variant="primary" :href="route('tickets.create')">
                <x-icon name="plus" class="btn__icon" />
                تذكرة جديدة
            </x-button>
        @endcan
    </div>

    @include('tickets.partials._filters-narrow')
</form>
