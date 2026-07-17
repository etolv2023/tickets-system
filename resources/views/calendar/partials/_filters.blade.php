<form method="GET" action="{{ route($routeName) }}" class="filters">
    <input type="hidden" name="view" value="{{ $view }}">
    <input type="hidden" name="date" value="{{ $anchor->toDateString() }}">

    @if ($isTeam)
        <select name="assignee" class="select filters__select">
            <option value="">كل الفريق</option>
            @foreach ($users as $person)
                <option value="{{ $person->id }}" @selected((string) ($filters['assignee'] ?? '') === (string) $person->id)>
                    {{ $person->name }}
                </option>
            @endforeach
        </select>
    @endif

    <select name="company" class="select filters__select">
        <option value="">كل الشركات</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected((string) ($filters['company'] ?? '') === (string) $company->id)>
                {{ $company->name }}
            </option>
        @endforeach
    </select>

    <select name="type" class="select filters__select">
        <option value="">كل الأنواع</option>
        @foreach (\App\Enums\TicketType::options() as $value => $label)
            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="priority" class="select filters__select">
        <option value="">كل الأولويات</option>
        @foreach (\App\Enums\Priority::options() as $value => $label)
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
