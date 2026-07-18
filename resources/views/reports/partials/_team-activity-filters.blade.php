{{-- Every filter lives in the query string (F03.1) — a filtered link is
     shareable, and each table keeps its own page number (tickets_page /
     subtasks_page) so paging one never resets the other. --}}
<form method="GET" action="{{ route('reports.team-activity') }}" class="filters">
    {{-- Searched server-side: both lists grow with the database. --}}
    <div class="filters__combobox">
        <x-combobox name="person" resource="users"
                    :value="$filters['person'] ?? null"
                    :selected="$selectedPerson ?? null"
                    placeholder="كل التيم" />
    </div>

    <div class="filters__group">
        <span class="u-subtle">من</span>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input" aria-label="من تاريخ">
        <span class="u-subtle">لـ</span>
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input" aria-label="لتاريخ">
    </div>

    <select name="show" class="select filters__select">
        <option value="both" @selected($show === 'both')>تذاكر وصب تاسكس</option>
        <option value="tickets" @selected($show === 'tickets')>تذاكر بس</option>
        <option value="subtasks" @selected($show === 'subtasks')>صب تاسكس بس</option>
    </select>

    <div class="filters__combobox">
        <x-combobox name="company" resource="companies"
                    :value="$filters['company'] ?? null"
                    :selected="$selectedCompany ?? null"
                    placeholder="كل الشركات" />
    </div>

    <select name="type" class="select filters__select">
        <option value="">كل الأنواع</option>
        @foreach ($types as $value => $label)
            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <x-button variant="secondary">فلترة</x-button>

    @if (array_filter($filters) || $show !== 'both')
        <x-button variant="ghost" :href="route('reports.team-activity')">مسح كل الفلاتر</x-button>
    @endif

    {{-- Filters specific to one table only — grouped so it's clear which
         table each one narrows. --}}
    <div class="filters__group filters__group--full">
        <span class="u-label">فلاتر التذاكر</span>

        <select name="ticket_date_basis" class="select filters__select">
            @foreach ($ticketDateBases as $value => $label)
                <option value="{{ $value }}" @selected(($filters['ticket_date_basis'] ?? 'reported_at') === $value)>
                    الفترة على: {{ $label }}
                </option>
            @endforeach
        </select>

        <select name="scope" class="select filters__select">
            <option value="">كل النطاقات</option>
            @foreach ($scopes as $value => $label)
                <option value="{{ $value }}" @selected(($filters['scope'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="priority" class="select filters__select">
            <option value="">كل الأولويات</option>
            @foreach ($priorities as $value => $label)
                <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="status" class="select filters__select">
            <option value="">كل الحالات</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="filters__group filters__group--full">
        <span class="u-label">فلاتر الصب تاسكس</span>

        <select name="subtask_date_basis" class="select filters__select">
            @foreach ($subtaskDateBases as $value => $label)
                <option value="{{ $value }}" @selected(($filters['subtask_date_basis'] ?? 'start_date') === $value)>
                    الفترة على: {{ $label }}
                </option>
            @endforeach
        </select>

        <select name="side" class="select filters__select">
            <option value="">كل الجهات</option>
            @foreach ($sides as $value => $label)
                <option value="{{ $value }}" @selected(($filters['side'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="subtask_status" class="select filters__select">
            <option value="">كل الحالات</option>
            @foreach ($subtaskStatuses as $value => $label)
                <option value="{{ $value }}" @selected(($filters['subtask_status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</form>
