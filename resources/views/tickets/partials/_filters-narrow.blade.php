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
    <div class="filters__dates" x-data="{ from: @js($filters['from'] ?? ''), to: @js($filters['to'] ?? '') }">
        <select name="date_basis" class="select" aria-label="الفترة على أي تاريخ">@foreach (\App\Models\Ticket::DATE_BASES as $value => $label)<option value="{{ $value }}" @selected(($filters['date_basis'] ?? 'reported_at') === $value)>{{ $label }}</option>@endforeach</select>
        <div class="filters__range">
            <span class="filters__range-label">من</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" x-model="from" :class="{ 'filters__date--empty': !from }" @class(['filters__date--empty' => empty($filters['from'])]) aria-label="من تاريخ">
            <span class="filters__range-label">إلى</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" x-model="to" :class="{ 'filters__date--empty': !to }" @class(['filters__date--empty' => empty($filters['to'])]) aria-label="إلى تاريخ">
        </div>
    </div>
</div>
