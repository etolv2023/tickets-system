{{-- The second line of the /tickets bar: what you NARROW BY. Split out of
     _filters.blade.php (CLAUDE.md § 3) when the bar grew its second row.

     ★ (2026-10-05) Two tiers. The first row is the everyday set that was always
     here; the "فلاتر أكتر" row holds the rest (label, who opened it, origin,
     approval, subtasks, lateness, module, order) and the date range with the
     column it runs against. The second tier opens by itself whenever one of
     its filters is live, so a shared link never hides the filter it carries. --}}
@php
    $advanced = ['label', 'creator', 'origin', 'approval', 'subtasks', 'late', 'module', 'sort', 'date_basis', 'from', 'to'];
    $moreOpen = (bool) array_filter(array_intersect_key($filters, array_flip($advanced)));
@endphp

<div class="filters__narrow" x-data="{ more: @js($moreOpen) }">
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
         owning a subtask on it are three different involvements. --}}
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

    <select name="culprit" class="select" aria-label="المتسبب في الاكسبشن">
        <option value="">متسبب الاكسبشن: الكل</option>
        @foreach($culpritUsers as $user)
            <option value="{{ $user->id }}" @selected((int) ($filters['culprit'] ?? 0) === $user->id)>{{ $user->name }}</option>
        @endforeach
    </select>

    {{-- ★ (2026-08-29) F27. Two states, both off the counter column. --}}
    @can('github.view')
        <select name="branch" class="select" aria-label="البرانش">
            <option value="">البرانش: الكل</option>
            <option value="none" @selected(($filters['branch'] ?? '') === 'none')>من غير برانش</option>
            <option value="has" @selected(($filters['branch'] ?? '') === 'has')>ليها برانش</option>
        </select>
    @endcan

    <select name="late" class="select" aria-label="التأخير">
        <option value="">التأخير: الكل</option>
        @foreach (\App\Models\Ticket::LATENESS as $value => $label)
            <option value="{{ $value }}" @selected(($filters['late'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <button type="button" class="btn btn--ghost btn--sm filters__more" @click="more = ! more" :aria-expanded="more.toString()">
        <x-icon name="filter" class="btn__icon" />
        <span x-text="more ? 'فلاتر أقل' : 'فلاتر أكتر'">{{ $moreOpen ? 'فلاتر أقل' : 'فلاتر أكتر' }}</span>
    </button>

    @if ($narrowing)
        <span class="filters__count">{{ count($narrowing) }} فلتر شغّال</span>
    @endif

    @include('tickets.partials._filters-more')
</div>
