{{-- ★ (2026-10-05) /reports used to take a month and nothing else. The month
     is still the frame; the rest narrows every card on the page together
     (ReportService::constrain) — except the two the controller leaves alone,
     which say so in their own headers. --}}
<form method="GET" action="{{ route('reports.index') }}" class="filters">
    <select name="period" class="select filters__select" aria-label="الشهر">
        @foreach ($months as $value => $label)
            <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <div class="filters__combobox">
        <x-combobox name="company" resource="companies"
                    :value="$filters['company'] ?? null"
                    :selected="$selectedCompany"
                    placeholder="كل الشركات" />
    </div>

    <select name="type" class="select filters__select" aria-label="النوع">
        <option value="">كل الأنواع</option>
        @foreach ($types as $value => $label)
            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="priority" class="select filters__select" aria-label="الأولوية">
        <option value="">كل الأولويات</option>
        @foreach ($priorities as $value => $label)
            <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <div class="filters__combobox">
        <x-combobox name="person" resource="users"
                    :value="$filters['person'] ?? null"
                    :selected="$selectedPerson"
                    placeholder="مسندة لأي حد" />
    </div>

    <x-button variant="secondary" size="sm">اعرض</x-button>

    @if (array_filter($filters))
        <x-button variant="ghost" size="sm" :href="route('reports.index', ['period' => $period])">مسح</x-button>
    @endif
</form>
