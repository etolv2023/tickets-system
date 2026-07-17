{{-- Every filter is a query-string key, so a filtered view is a shareable
     link — send your colleague exactly what you're looking at. F03.1 --}}
<form method="GET" action="{{ route('tickets.index') }}" class="filters">
    <input
        type="search"
        name="q"
        value="{{ $filters['q'] ?? '' }}"
        placeholder="ابحث بالعنوان أو الوصف، أو الصق رقم تذكرة"
        class="input filters__search"
    >

    <select name="status" class="select filters__select">
        <option value="">كل الحالات</option>
        <option value="open" @selected(($filters['status'] ?? '') === 'open')>غير محلولة</option>
        <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>محلولة</option>
        @foreach (\App\Enums\TicketStatus::options() as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
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

    <select name="scope" class="select filters__select">
        <option value="">كل النطاقات</option>
        @foreach (\App\Enums\TicketScope::options() as $value => $label)
            <option value="{{ $value }}" @selected(($filters['scope'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <select name="company" class="select filters__select">
        <option value="">كل الشركات</option>
        @foreach ($companies as $company)
            <option value="{{ $company->id }}" @selected((string) ($filters['company'] ?? '') === (string) $company->id)>
                {{ $company->name }}
            </option>
        @endforeach
    </select>

    <div class="filters__group">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input" aria-label="من تاريخ">
        <span class="u-subtle">→</span>
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input" aria-label="لتاريخ">
    </div>

    <x-button variant="secondary">فلترة</x-button>

    @if (array_filter($filters))
        <x-button variant="ghost" :href="route('tickets.index')">مسح</x-button>
    @endif
</form>
