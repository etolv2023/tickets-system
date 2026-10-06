@php
    $mine = (int) ($filters['assignee'] ?? 0) === auth()->id()
        && in_array($filters['relation'] ?? 'any', ['any', ''], true);
@endphp

<div class="filters__bar">
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
           placeholder="ابحث بالعنوان أو الوصف، أو الصق رقم تذكرة" class="input filters__search">
    @if ($canSeeOthers)
        <a href="{{ $mine ? route('tickets.index') : route('tickets.index', ['assignee' => auth()->id(), 'relation' => 'any']) }}"
           @class(['btn', 'btn--secondary', 'filters__mine', 'filters__mine--on' => $mine])
           @if ($mine) aria-pressed="true" @endif>
            <x-icon name="user" class="btn__icon" /> تذاكري
        </a>
    @endif
    <x-button variant="secondary">فلترة</x-button>
    @if (array_filter($filters))
        <x-button variant="ghost" :href="route('tickets.index')">مسح</x-button>
    @endif
    <x-export-button route="export.tickets" />
    @can('create', App\Models\Ticket::class)
        <x-button variant="primary" :href="route('tickets.create')"><x-icon name="plus" class="btn__icon" /> تذكرة جديدة</x-button>
    @endcan
</div>

<div class="filters__narrow">
    <select name="status" class="select" aria-label="الحالة">
        <option value="">كل الحالات</option>
        <option value="open" @selected(($filters['status'] ?? '') === 'open')>غير محلولة</option>
        <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>محلولة</option>
        @foreach (\App\Models\TicketStatusDefinition::options() as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select name="type" class="select" aria-label="النوع"><option value="">كل الأنواع</option>@foreach (\App\Models\TicketTypeDefinition::options() as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
    <select name="priority" class="select" aria-label="الأولوية"><option value="">كل الأولويات</option>@foreach (\App\Models\PriorityDefinition::options() as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
    <div class="filters__combobox"><x-combobox name="company" resource="companies" :value="$filters['company'] ?? null" :selected="$selectedCompany" placeholder="كل الشركات" /></div>
    <div class="filters__person">
        <div class="filters__combobox"><x-combobox name="assignee" resource="users" :value="$filters['assignee'] ?? null" :selected="$selectedAssignee" placeholder="أي شخص" /></div>
        <select name="relation" class="select" aria-label="علاقته بالتذكرة">@foreach (\App\Models\Ticket::RELATIONS as $value => $label)<option value="{{ $value }}" @selected(($filters['relation'] ?? 'any') === $value)>{{ $label }}</option>@endforeach</select>
    </div>
    <div class="filters__dates">
        <select name="date_basis" class="select" aria-label="الفترة على أي تاريخ">@foreach (\App\Models\Ticket::DATE_BASES as $value => $label)<option value="{{ $value }}" @selected(($filters['date_basis'] ?? 'reported_at') === $value)>{{ $label }}</option>@endforeach</select>
        <div class="filters__range">
            <span class="filters__range-label">من</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="من تاريخ">
            <span class="filters__range-label">إلى</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="إلى تاريخ">
        </div>
    </div>
</div>
