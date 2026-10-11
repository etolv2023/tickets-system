<div class="filters__narrow">
    <select name="status" class="select" aria-label="{{ __('الحالة') }}">
        <option value="">{{ __('كل الحالات') }}</option>
        <option value="open" @selected(($filters['status'] ?? '') === 'open')>{{ __('غير محلولة') }}</option>
        <option value="resolved" @selected(($filters['status'] ?? '') === 'resolved')>{{ __('محلولة') }}</option>
        @foreach (\App\Models\TicketStatusDefinition::options() as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __($label) }}</option>
        @endforeach
    </select>
    <select name="type" class="select" aria-label="{{ __('النوع') }}"><option value="">{{ __('كل الأنواع') }}</option>@foreach (\App\Models\TicketTypeDefinition::options() as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
    <select name="priority" class="select" aria-label="{{ __('الأولوية') }}"><option value="">{{ __('كل الأولويات') }}</option>@foreach (\App\Models\PriorityDefinition::options() as $value => $label)<option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
    <div class="filters__combobox"><x-combobox name="company" resource="companies" :value="$filters['company'] ?? null" :selected="$selectedCompany" :placeholder="__('كل الشركات')" /></div>
    <div class="filters__person">
        <div class="filters__combobox"><x-combobox name="assignee" resource="users" :value="$filters['assignee'] ?? null" :selected="$selectedAssignee" :placeholder="__('أي شخص')" /></div>
        <select name="relation" class="select" aria-label="{{ __('علاقته بالتذكرة') }}">@foreach (\App\Models\Ticket::RELATIONS as $value => $label)<option value="{{ $value }}" @selected(($filters['relation'] ?? 'any') === $value)>{{ __($label) }}</option>@endforeach</select>
    </div>
    <div class="filters__dates" x-data="{ from: @js($filters['from'] ?? ''), to: @js($filters['to'] ?? '') }">
        <select name="date_basis" class="select" aria-label="{{ __('الفترة على أي تاريخ') }}">@foreach (\App\Models\Ticket::DATE_BASES as $value => $label)<option value="{{ $value }}" @selected(($filters['date_basis'] ?? 'reported_at') === $value)>{{ __($label) }}</option>@endforeach</select>
        <div class="filters__range">
            <span class="filters__range-label">{{ __('من') }}</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" x-model="from" :class="{ 'filters__date--empty': !from }" @class(['filters__date--empty' => empty($filters['from'])]) aria-label="{{ __('من تاريخ') }}">
            <span class="filters__range-label">{{ __('إلى') }}</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" x-model="to" :class="{ 'filters__date--empty': !to }" @class(['filters__date--empty' => empty($filters['to'])]) aria-label="{{ __('إلى تاريخ') }}">
        </div>
    </div>
</div>
