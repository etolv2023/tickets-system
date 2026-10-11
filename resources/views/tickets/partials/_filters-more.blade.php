{{-- The "فلاتر أكتر" tier of the /tickets bar. Rendered inside
     _filters-narrow's grid and Alpine scope: `more` decides whether it shows,
     and every control here is a plain query-string key like the rest. --}}
<div class="filters__tier" x-show="more" x-cloak>
    <div class="filters__combobox">
        <x-combobox name="label" resource="labels"
                    :value="$filters['label'] ?? null"
                    :selected="$selectedLabel"
                    :placeholder="__('أي لابل')" />
    </div>

    <div class="filters__combobox">
        <x-combobox name="creator" resource="users"
                    :value="$filters['creator'] ?? null"
                    :selected="$selectedCreator"
                    :placeholder="__('فتحها: أي حد')" />
    </div>

    <select name="origin" class="select" aria-label="{{ __('مصدر التذكرة') }}">
        <option value="">{{ __('المصدر: الكل') }}</option>
        @foreach (\App\Models\Ticket::ORIGINS as $value => $label)
            <option value="{{ $value }}" @selected(($filters['origin'] ?? '') === $value)>{{ __($label) }}</option>
        @endforeach
    </select>

    <select name="approval" class="select" aria-label="{{ __('الموافقة') }}">
        <option value="">{{ __('الموافقة: الكل') }}</option>
        @foreach (\App\Models\Ticket::APPROVALS as $value => $label)
            <option value="{{ $value }}" @selected(($filters['approval'] ?? '') === $value)>{{ __($label) }}</option>
        @endforeach
    </select>

    <select name="subtasks" class="select" aria-label="{{ __('الصب تاسكس') }}">
        <option value="">{{ __('الصب تاسكس: الكل') }}</option>
        @foreach (\App\Models\Ticket::SUBTASK_STATES as $value => $label)
            <option value="{{ $value }}" @selected(($filters['subtasks'] ?? '') === $value)>{{ __($label) }}</option>
        @endforeach
    </select>

    <input type="search" name="module" value="{{ $filters['module'] ?? '' }}" class="input"
           placeholder="{{ __('الموديول…') }}" aria-label="{{ __('الموديول') }}">

    <select name="sort" class="select" aria-label="{{ __('الترتيب') }}">
        @foreach (\App\Models\Ticket::SORTS as $value => $label)
            <option value="{{ $value }}" @selected(($filters['sort'] ?? 'default') === $value)>{{ __('الترتيب: :sort', ['sort' => __($label)]) }}</option>
        @endforeach
    </select>

    {{-- The range and the column it runs against are one question, so they
         share one cell: "تاريخ الحل من … إلى …". --}}
    <div class="filters__dates">
        <select name="date_basis" class="select" aria-label="{{ __('الفترة على أي تاريخ') }}">
            @foreach (\App\Models\Ticket::DATE_BASES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['date_basis'] ?? 'reported_at') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
        <div class="filters__range">
            <span class="filters__range-label">{{ __('من') }}</span>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="{{ __('من تاريخ') }}">
            <span class="filters__range-label">{{ __('إلى') }}</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="{{ __('لتاريخ') }}">
        </div>
    </div>
</div>
