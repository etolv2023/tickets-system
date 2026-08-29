{{-- Every filter is a query-string key, so a filtered view is a shareable
     link — send your colleague exactly what you're looking at. F03.1

     ★ (2026-08-02) Was one flat flex-wrap row of ten controls with no grouping:
     the search box, six selects, two dates and three buttons all wrapped at
     whatever width the viewport happened to give them, so nothing sat where you
     last saw it. Now it reads as two deliberate lines — what you DO on top
     (search, my tickets, the primary action) and what you NARROW BY underneath
     — and the narrowing line is one grid, so the controls land in the same
     place every time. --}}
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

    <div class="filters__narrow">
        <select name="status" class="select" aria-label="الحالة">
            <option value="">كل الحالات</option>
            <option value="open" @selected(($filters['status'] ?? '') === 'open')>غير محلولة</option>
            <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>محلولة</option>
            @foreach (\App\Models\TicketStatusDefinition::options() as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="type" class="select" aria-label="النوع">
            <option value="">كل الأنواع</option>
            @foreach (\App\Models\TicketTypeDefinition::options() as $value => $label)
                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="priority" class="select" aria-label="الأولوية">
            <option value="">كل الأولويات</option>
            @foreach (\App\Models\PriorityDefinition::options() as $value => $label)
                <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        {{-- Looked up server-side: this list grows with the customer table. --}}
        <div class="filters__combobox">
            <x-combobox name="company" resource="companies"
                        :value="$filters['company'] ?? null"
                        :selected="$selectedCompany"
                        placeholder="كل الشركات" />
        </div>

        {{-- The person and HOW they're attached, kept adjacent on purpose: the
             name alone is ambiguous — holding a role, opening the ticket and
             owning a subtask on it are three different involvements, and the
             old bar could only ask about the first. --}}
        <div class="filters__person">
            <div class="filters__combobox">
                <x-combobox name="assignee" resource="users"
                            :value="$filters['assignee'] ?? null"
                            :selected="$selectedAssignee"
                            placeholder="أي شخص" />
            </div>

            <select name="relation" class="select" aria-label="علاقته بالتذكرة">
                @foreach (\App\Models\Ticket::RELATIONS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['relation'] ?? 'any') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="filters__group">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input" aria-label="من تاريخ">
            <span class="u-subtle">→</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input" aria-label="لتاريخ">
        </div>

        @if ($narrowing)
            <span class="filters__count">{{ count($narrowing) }} فلتر شغّال</span>
        @endif
    </div>
</form>
