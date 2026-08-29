{{-- The full ticket filter set, on the "no branch" screen.

     Deliberately the same controls, the same query-string keys and the same
     order as tickets/partials/_filters — the two screens ask the same questions
     about the same rows, and a person who learned one bar should not have to
     learn a second. What is different is only what this screen adds: the
     repository, and a status default that means "settled" rather than "all".

     Not the same FILE, though. That one carries the search box, "تذاكري", the
     export and the "تذكرة جديدة" button, none of which belong here — sharing it
     would mean a partial full of @if($screen === …), which is worse than two
     honest files. --}}

<form method="GET" action="{{ route('github.branches') }}" class="filters">
    <div class="filters__bar">
        <input
            type="search"
            name="q"
            value="{{ $filters['q'] ?? '' }}"
            placeholder="ابحث بالعنوان أو الوصف، أو الصق رقم تذكرة"
            class="input filters__search"
        >

        <x-button variant="secondary">فلترة</x-button>

        @if (array_filter(\Illuminate\Support\Arr::except($filters, ['date_basis'])))
            <x-button variant="ghost" :href="route('github.branches')">مسح</x-button>
        @endif

        {{-- «زامن دلوقتي» is NOT here. It is a POST, this is a GET, and HTML has
             no nested forms — the browser silently drops the inner one, so the
             button would render and do nothing. It lives in the page header. --}}
    </div>

    <div class="filters__narrow">
        {{-- ★ (2026-08-29) The question itself, first: ملهاش / ليها / الكل.
             Blank stays «ملهاش برانش» — the blank state of this screen is the
             question it was built to answer. --}}
        <select name="branch" class="select" aria-label="البرانش">
            @foreach ($branchModes as $value => $label)
                <option value="{{ $value }}" @selected($mode === $value)>{{ $label }}</option>
            @endforeach
        </select>

        {{-- Blank means "اتقفلت" here, not "everything": the default state of
             this screen is the question it exists to answer. --}}
        <select name="status" class="select" aria-label="الحالة">
            <option value="">اتقفلت (محلولة أو مغلقة)</option>
            <option value="all" @selected(($filters['status'] ?? '') === 'all')>كل الحالات</option>
            <option value="open" @selected(($filters['status'] ?? '') === 'open')>لسه مفتوحة</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        {{-- Scopes the question above to one repository: «ملهاش برانش في الباك
             اند» is a different question from «ملهاش برانش خالص», and on a team
             split across a backend repo and three frontends it is usually the
             more useful one. The wording follows the mode so the two selects
             read as one sentence rather than contradicting each other. --}}
        <select name="repo" class="select" aria-label="الريبو">
            <option value="">في أي ريبو</option>
            @foreach ($repositories as $repository)
                <option value="{{ $repository->id }}" @selected((int) ($filters['repo'] ?? 0) === $repository->id)>
                    في {{ $repository->name }}
                </option>
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

        {{-- Its own cell, NOT inside .filters__group below.

             That group is sized for exactly two date inputs and an arrow across
             two grid tracks — the inputs are flex: 1 1 0 so they can shrink
             rather than overflow the row (see filters.css). Putting a third
             control in there gave the select its full width and squeezed both
             dates down to empty squares. --}}
        <select name="date_basis" class="select" aria-label="التاريخ محسوب على">
            @foreach (\App\Models\Ticket::DATE_BASES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['date_basis'] ?? 'resolved_at') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        <div class="filters__group">
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input" aria-label="من تاريخ">
            <span class="u-subtle">→</span>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input" aria-label="لتاريخ">
        </div>
    </div>
</form>
