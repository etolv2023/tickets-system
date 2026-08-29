{{-- The tickets-list filter bar, minus what this queue already decides.

     Same query-string keys, same order, same controls as
     tickets/partials/_filters — a person who learned that bar should not have to
     learn a second one. What is missing is deliberate: no «تذاكري» shortcut (the
     list is already scoped by visibleTo), no «تذكرة جديدة» (this is a queue, not
     a place you create from), and the status select offers only the statuses a
     ticket in this queue can actually be in. --}}

<form method="GET" action="{{ route('queues.ready') }}" class="filters">
    <div class="filters__bar">
        <input
            type="search"
            name="q"
            value="{{ $filters['q'] ?? '' }}"
            placeholder="ابحث بالعنوان أو الوصف، أو الصق رقم تذكرة"
            class="input filters__search"
        >

        <x-button variant="secondary">فلترة</x-button>

        @if (array_filter($filters))
            <x-button variant="ghost" :href="route('queues.ready')">مسح</x-button>
        @endif
    </div>

    <div class="filters__narrow">
        <select name="status" class="select" aria-label="الحالة">
            <option value="">كل الحالات المفتوحة</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="type" class="select" aria-label="النوع">
            <option value="">كل الأنواع</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <select name="priority" class="select" aria-label="الأولوية">
            <option value="">كل الأولويات</option>
            @foreach ($priorities as $value => $label)
                <option value="{{ $value }}" @selected(($filters['priority'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <div class="filters__combobox">
            <x-combobox name="company" resource="companies"
                        :value="$filters['company'] ?? null"
                        :selected="$selectedCompany"
                        placeholder="كل الشركات" />
        </div>

        <div class="filters__person">
            <div class="filters__combobox">
                <x-combobox name="assignee" resource="users"
                            :value="$filters['assignee'] ?? null"
                            :selected="$selectedAssignee"
                            placeholder="أي شخص" />
            </div>

            <select name="relation" class="select" aria-label="علاقته بالتذكرة">
                @foreach (\App\Models\Ticket::RELATIONS as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['relation'] ?? 'any') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="filters__group">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input" aria-label="من تاريخ">
            <span class="u-subtle">→</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input" aria-label="لتاريخ">
        </div>
    </div>
</form>
