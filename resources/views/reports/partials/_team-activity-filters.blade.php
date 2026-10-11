{{-- Every filter lives in the query string (F03.1) — a filtered link is
     shareable, and each table keeps its own page number (tickets_page /
     subtasks_page) so paging one never resets the other. --}}
<form method="GET" action="{{ route('reports.team-activity') }}" class="filters">
    {{-- Searched server-side: both lists grow with the database. --}}
    <div class="filters__combobox">
        <x-combobox name="person" resource="users"
                    :value="$filters['person'] ?? null"
                    :selected="$selectedPerson ?? null"
                    :placeholder="__('الفريق بالكامل')" />
    </div>

    <div class="filters__group">
        <span class="u-subtle">{{ __('من') }}</span>
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input" aria-label="{{ __('من تاريخ') }}">
        <span class="u-subtle">{{ __('إلى') }}</span>
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input" aria-label="{{ __('حتى تاريخ') }}">
    </div>

    <select name="show" class="select filters__select">
        <option value="both" @selected($show === 'both')>{{ __('التذاكر والمهام الفرعية') }}</option>
        <option value="tickets" @selected($show === 'tickets')>{{ __('التذاكر فقط') }}</option>
        <option value="subtasks" @selected($show === 'subtasks')>{{ __('المهام الفرعية فقط') }}</option>
    </select>

    <div class="filters__combobox">
        <x-combobox name="company" resource="companies"
                    :value="$filters['company'] ?? null"
                    :selected="$selectedCompany ?? null"
                    :placeholder="__('جميع الشركات')" />
    </div>

    <select name="type" class="select filters__select">
        <option value="">{{ __('كل الأنواع') }}</option>
        @foreach ($types as $value => $label)
            <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ __($label) }}</option>
        @endforeach
    </select>

    {{-- ★ (2026-10-05) One box over both tables: a subtask title, or its
         ticket's number or title. --}}
    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input filters__search"
           placeholder="{{ __('ابحث بعنوان المهمة الفرعية أو التذكرة أو رقمها') }}" aria-label="{{ __('بحث') }}">

    <x-button variant="secondary">{{ __('تصفية') }}</x-button>

    @if (array_filter($filters) || $show !== 'both')
        <x-button variant="ghost" :href="route('reports.team-activity')">{{ __('مسح جميع عوامل التصفية') }}</x-button>
    @endif

    {{-- Filters specific to one table only — grouped so it's clear which
         table each one narrows. --}}
    <div class="filters__group filters__group--full">
        <span class="u-label">{{ __('عوامل تصفية التذاكر') }}</span>

        <select name="ticket_date_basis" class="select filters__select">
            @foreach ($ticketDateBases as $value => $label)
                <option value="{{ $value }}" @selected(($filters['ticket_date_basis'] ?? 'reported_at') === $value)>
                    {{ __('الفترة حسب: :basis', ['basis' => __($label)]) }}
                </option>
            @endforeach
        </select>

        <select name="priority" class="select filters__select">
            <option value="">{{ __('جميع الأولويات') }}</option>
            @foreach ($priorities as $value => $label)
                <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>

        <select name="status" class="select filters__select">
            <option value="">{{ __('جميع الحالات') }}</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>

        {{-- ★ (2026-10-05) Missed its SLA or delivery date — Ticket::scopeLate. --}}
        <select name="ticket_late" class="select filters__select" aria-label="{{ __('تأخير التذكرة') }}">
            <option value="">{{ __('التأخير: الكل') }}</option>
            @foreach ($lateness as $value => $label)
                <option value="{{ $value }}" @selected(($filters['ticket_late'] ?? '') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>
    </div>

    <div class="filters__group filters__group--full">
        <span class="u-label">{{ __('عوامل تصفية المهام الفرعية') }}</span>

        <select name="subtask_date_basis" class="select filters__select">
            @foreach ($subtaskDateBases as $value => $label)
                <option value="{{ $value }}" @selected(($filters['subtask_date_basis'] ?? 'start_date') === $value)>
                    {{ __('الفترة حسب: :basis', ['basis' => __($label)]) }}
                </option>
            @endforeach
        </select>

        <select name="role" class="select filters__select">
            <option value="">{{ __('جميع الأدوار') }}</option>
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected((int) ($filters['role'] ?? 0) === $role->id)>{{ $role->label() }}</option>
            @endforeach
        </select>

        <select name="subtask_status" class="select filters__select">
            <option value="">{{ __('جميع الحالات') }}</option>
            @foreach ($subtaskStatuses as $value => $label)
                <option value="{{ $value }}" @selected(($filters['subtask_status'] ?? '') === $value)>{{ __($label) }}</option>
            @endforeach
        </select>

        {{-- ★ (2026-10-05) Still open past its date, or not — TicketSubtask::isOverdue(). --}}
        <select name="subtask_overdue" class="select filters__select" aria-label="{{ __('تأخير المهمة الفرعية') }}">
            <option value="">{{ __('التأخير: الكل') }}</option>
            <option value="yes" @selected(($filters['subtask_overdue'] ?? '') === 'yes')>{{ __('متأخرة') }}</option>
            <option value="no" @selected(($filters['subtask_overdue'] ?? '') === 'no')>{{ __('غير متأخرة') }}</option>
        </select>
    </div>
</form>
