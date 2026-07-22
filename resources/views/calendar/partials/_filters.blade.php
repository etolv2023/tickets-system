<form method="GET" action="{{ route($routeName) }}" class="filters">
    <input type="hidden" name="view" value="{{ $view }}">
    <input type="hidden" name="date" value="{{ $anchor->toDateString() }}">

    {{-- Searched server-side: both of these lists grow with the database. --}}
    @if ($isTeam)
        <div class="filters__combobox">
            <x-combobox name="assignee" resource="users"
                        :value="$filters['assignee'] ?? null"
                        :selected="$selectedAssignee ?? null"
                        placeholder="كل الفريق" />
        </div>
    @endif

    {{-- What kind of thing to draw. First in the row because it changes what
         every other filter is even filtering. --}}
    <select name="show" class="select filters__select">
        @foreach (['all' => 'تذاكر وصب تاسكس', 'subtasks' => 'صب تاسكس بس', 'tickets' => 'تذاكر بس'] as $value => $label)
            <option value="{{ $value }}" @selected(($filters['show'] ?? 'all') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <div class="filters__combobox">
        <x-combobox name="company" resource="companies"
                    :value="$filters['company'] ?? null"
                    :selected="$selectedCompany ?? null"
                    placeholder="كل الشركات" />
    </div>

    <select name="type" class="select filters__select">
        <option value="">كل الأنواع</option>
        @foreach (\App\Models\TicketTypeDefinition::options() as $value => $label)
            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="priority" class="select filters__select">
        <option value="">كل الأولويات</option>
        @foreach (\App\Models\PriorityDefinition::options() as $value => $label)
            <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="side" class="select filters__select">
        <option value="">كل الجهات</option>
        @foreach ($sides as $value => $label)
            <option value="{{ $value }}" @selected(($filters['side'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <x-button variant="secondary">فلترة</x-button>

    @if (array_filter($filters))
        <x-button variant="ghost" :href="route($routeName, ['view' => $view])">مسح</x-button>
    @endif
</form>
