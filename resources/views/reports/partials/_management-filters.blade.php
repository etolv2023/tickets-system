<form method="GET" action="{{ url()->current() }}" class="filters reports-filters">
    @isset($user)
        <div class="filters__combobox">
            <x-combobox name="user" resource="users" :value="$user->id" :selected="$user" :placeholder="__('الموظف')" />
        </div>
    @endisset
    @isset($by)
        <select name="by" class="select filters__select" aria-label="{{ __('التجميع') }}">
            @foreach (['type' => __('النوع'), 'status' => __('الحالة'), 'priority' => __('الأولوية'), 'module' => __('الوحدة')] as $key => $label)
                <option value="{{ $key }}" @selected($by === $key)>{{ __($label) }}</option>
            @endforeach
        </select>
    @endisset
    <select name="date_basis" class="select filters__select" aria-label="{{ __('نوع التاريخ') }}">
        @foreach ($dateBases as $key => $label)
            <option value="{{ $key }}" @selected($dateBasis === $key)>{{ __($label) }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ $from }}" class="input filters__date" aria-label="{{ __('من') }}">
    <input type="date" name="to" value="{{ $to }}" class="input filters__date" aria-label="{{ __('إلى') }}">
    <input name="type" value="{{ $filters['type'] ?? '' }}" class="input filters__select" placeholder="{{ __('مفتاح النوع') }}">
    <input name="status" value="{{ $filters['status'] ?? '' }}" class="input filters__select" placeholder="{{ __('مفتاح الحالة') }}">
    <input name="priority" value="{{ $filters['priority'] ?? '' }}" class="input filters__select" placeholder="{{ __('مفتاح الأولوية') }}">
    <input name="module" value="{{ $filters['module'] ?? '' }}" class="input filters__select" placeholder="{{ __('الوحدة') }}">
    <div class="filters__combobox">
        <x-combobox name="assignee" resource="users" :value="$filters['assignee'] ?? null"
                    :selected="$selectedAssignee" :placeholder="__('جميع المسؤولين')" />
    </div>
    <select name="deadline" class="select filters__select" aria-label="{{ __('حالة الموعد') }}">
        <option value="">{{ __('جميع المواعيد') }}</option>
        @foreach ($deadlineFilters as $key => $label)
            <option value="{{ $key }}" @selected(($filters['deadline'] ?? '') === $key)>{{ __($label) }}</option>
        @endforeach
    </select>
    <x-button variant="secondary" size="sm">{{ __('عرض') }}</x-button>
</form>
