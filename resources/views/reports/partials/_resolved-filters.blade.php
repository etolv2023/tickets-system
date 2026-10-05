{{-- Every filter is a query-string key (F03.1) so a filtered view is a link.
     The quick ranges are links too: each one is the same form with its dates
     filled in, not a control with its own state. --}}
<form method="GET" action="{{ route('reports.resolved-by-type') }}" class="filters">
    <div class="filters__combobox">
        <x-combobox name="person" resource="users"
                    :value="$filters['person'] ?? null"
                    :selected="$selectedPerson"
                    placeholder="كل التيم" />
    </div>

    <select name="relation" class="select filters__select" aria-label="علاقته بالتذكرة">
        @foreach ($relations as $value => $label)
            <option value="{{ $value }}" @selected($data['relation'] === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <div class="filters__range">
        <span class="filters__range-label">من</span>
        <input type="date" name="from" value="{{ $filters['from'] }}" aria-label="من تاريخ">
        <span class="filters__range-label">إلى</span>
        <input type="date" name="to" value="{{ $filters['to'] }}" aria-label="لتاريخ">
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
        <x-combobox name="company" resource="companies"
                    :value="$filters['company'] ?? null"
                    :selected="$selectedCompany"
                    placeholder="كل الشركات" />
    </div>

    <x-button variant="secondary" size="sm">اعرض</x-button>

    @if (array_filter(array_diff_key($filters, ['from' => 1, 'to' => 1, 'relation' => 1])) || request()->hasAny(['from', 'to']))
        <x-button variant="ghost" size="sm" :href="route('reports.resolved-by-type')">مسح</x-button>
    @endif

    <div class="filters__group filters__group--full">
        <span class="u-label">فترة سريعة</span>
        @foreach ($quickRanges as $range)
            @php($active = $filters['from'] === $range['from'] && $filters['to'] === $range['to'])
            <a href="{{ route('reports.resolved-by-type', array_filter($filters) + ['from' => $range['from'], 'to' => $range['to']]) }}"
               @class(['btn', 'btn--ghost', 'btn--sm', 'filters__mine--on' => $active])
               @if ($active) aria-pressed="true" @endif>
                {{ $range['label'] }}
            </a>
        @endforeach
    </div>
</form>
