<form method="GET" action="{{ url()->current() }}" class="filters reports-filters">
    @isset($user)
        <div class="filters__combobox">
            <x-combobox name="user" resource="users" :value="$user->id" :selected="$user" placeholder="الموظف" />
        </div>
    @endisset
    @isset($by)
        <select name="by" class="select filters__select" aria-label="التجميع">
            @foreach (['type' => 'النوع', 'status' => 'الحالة', 'priority' => 'الأولوية', 'module' => 'الموديول'] as $key => $label)
                <option value="{{ $key }}" @selected($by === $key)>{{ $label }}</option>
            @endforeach
        </select>
    @endisset
    <select name="date_basis" class="select filters__select" aria-label="نوع التاريخ">
        @foreach ($dateBases as $key => $label)
            <option value="{{ $key }}" @selected($dateBasis === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <input type="date" name="from" value="{{ $from }}" class="input filters__date" aria-label="من">
    <input type="date" name="to" value="{{ $to }}" class="input filters__date" aria-label="إلى">
    <input name="type" value="{{ $filters['type'] ?? '' }}" class="input filters__select" placeholder="مفتاح النوع">
    <input name="status" value="{{ $filters['status'] ?? '' }}" class="input filters__select" placeholder="مفتاح الحالة">
    <input name="priority" value="{{ $filters['priority'] ?? '' }}" class="input filters__select" placeholder="مفتاح الأولوية">
    <input name="module" value="{{ $filters['module'] ?? '' }}" class="input filters__select" placeholder="الموديول">
    <div class="filters__combobox">
        <x-combobox name="assignee" resource="users" :value="$filters['assignee'] ?? null"
                    :selected="$selectedAssignee" placeholder="كل المسؤولين" />
    </div>
    <select name="deadline" class="select filters__select" aria-label="حالة الموعد">
        <option value="">كل المواعيد</option>
        @foreach ($deadlineFilters as $key => $label)
            <option value="{{ $key }}" @selected(($filters['deadline'] ?? '') === $key)>{{ $label }}</option>
        @endforeach
    </select>
    <x-button variant="secondary" size="sm">اعرض</x-button>
</form>
