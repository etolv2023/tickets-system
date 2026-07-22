{{-- The board's filter bar. Status is deliberately absent — the columns are
     the statuses — so this narrows by type/priority/company/search (and, on the
     team board, by assignee). $filters and the selected lookups come from
     BoardController; no query lives in the view. § 3 --}}
<form method="GET" action="{{ route($routeName) }}" class="filters">
    @isset($lane)
        {{-- Keep the swimlane grouping across a filter submit. --}}
        <input type="hidden" name="lane" value="{{ $lane }}">
    @endisset

    <input
        type="search"
        name="q"
        value="{{ $filters['q'] ?? '' }}"
        placeholder="ابحث بالعنوان أو الوصف، أو الصق رقم تذكرة"
        class="input filters__search"
    >

    @if ($isTeam)
        <div class="filters__combobox">
            <x-combobox name="assignee" resource="users"
                        :value="$filters['assignee'] ?? null"
                        :selected="$selectedAssignee ?? null"
                        placeholder="كل المسؤولين" />
        </div>
    @endif

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

    <x-button variant="secondary">فلترة</x-button>

    @if (array_filter($filters))
        <x-button variant="ghost" :href="route($routeName, isset($lane) ? ['lane' => $lane] : [])">مسح</x-button>
    @endif
</form>
