{{-- The "فلاتر أكتر" tier of the /tickets bar. Rendered inside
     _filters-narrow's grid and Alpine scope: `more` decides whether it shows,
     and every control here is a plain query-string key like the rest. --}}
<div class="filters__tier" x-show="more" x-cloak>
    <div class="filters__combobox">
        <x-combobox name="label" resource="labels"
                    :value="$filters['label'] ?? null"
                    :selected="$selectedLabel"
                    placeholder="أي لابل" />
    </div>

    <div class="filters__combobox">
        <x-combobox name="creator" resource="users"
                    :value="$filters['creator'] ?? null"
                    :selected="$selectedCreator"
                    placeholder="فتحها: أي حد" />
    </div>

    <select name="origin" class="select" aria-label="مصدر التذكرة">
        <option value="">المصدر: الكل</option>
        @foreach (\App\Models\Ticket::ORIGINS as $value => $label)
            <option value="{{ $value }}" @selected(($filters['origin'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="approval" class="select" aria-label="الموافقة">
        <option value="">الموافقة: الكل</option>
        @foreach (\App\Models\Ticket::APPROVALS as $value => $label)
            <option value="{{ $value }}" @selected(($filters['approval'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="subtasks" class="select" aria-label="الصب تاسكس">
        <option value="">الصب تاسكس: الكل</option>
        @foreach (\App\Models\Ticket::SUBTASK_STATES as $value => $label)
            <option value="{{ $value }}" @selected(($filters['subtasks'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <input type="search" name="module" value="{{ $filters['module'] ?? '' }}" class="input"
           placeholder="الموديول…" aria-label="الموديول">

    <select name="sort" class="select" aria-label="الترتيب">
        @foreach (\App\Models\Ticket::SORTS as $value => $label)
            <option value="{{ $value }}" @selected(($filters['sort'] ?? 'default') === $value)>الترتيب: {{ $label }}</option>
        @endforeach
    </select>

    {{-- The range and the column it runs against are one question, so they
         share one cell: "تاريخ الحل من … إلى …". --}}
    <div class="filters__dates">
        <select name="date_basis" class="select" aria-label="الفترة على أي تاريخ">
            @foreach (\App\Models\Ticket::DATE_BASES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['date_basis'] ?? 'reported_at') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="filters__range">
            <span class="filters__range-label">من</span>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="من تاريخ">
            <span class="filters__range-label">إلى</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="لتاريخ">
        </div>
    </div>
</div>
