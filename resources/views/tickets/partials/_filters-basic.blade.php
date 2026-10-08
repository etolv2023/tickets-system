@php
    $mine = (int) ($filters['assignee'] ?? 0) === auth()->id()
        && in_array($filters['relation'] ?? 'any', ['any', ''], true);
@endphp

<div class="filters__bar">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
           placeholder="ابحث بالعنوان أو الوصف، أو الصق رقم تذكرة" class="input filters__search">
    @if ($canSeeOthers)
        <a href="{{ $mine ? route('tickets.index') : route('tickets.index', ['assignee' => auth()->id(), 'relation' => 'any']) }}"
           @class(['btn', 'btn--secondary', 'filters__mine', 'filters__mine--on' => $mine])
           @if ($mine) aria-pressed="true" @endif>
            <x-icon name="user" class="btn__icon" /> تذاكري
        </a>
    @endif
    <button type="button" class="btn btn--secondary filters__panel-toggle"
            @click="panelOpen = !panelOpen" :aria-expanded="panelOpen.toString()">
        <x-icon name="filter" class="btn__icon" />
        الفلاتر
        @if ($activeFilterCount)
            <span class="filters__active-count">{{ $activeFilterCount }}</span>
        @endif
    </button>
    <x-export-button route="export.tickets" />
    @can('create', App\Models\Ticket::class)
        <x-button variant="primary" :href="route('tickets.create')"><x-icon name="plus" class="btn__icon" /> تذكرة جديدة</x-button>
    @endcan
</div>
