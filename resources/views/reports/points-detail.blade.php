@extends('layouts.app')

@section('title', 'تقرير النقاط التفصيلي')

@php
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

@section('content')
    {{-- ★★★★ (2026-07-21): paired report-card grids, same case as /reports. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">تقرير النقاط التفصيلي</h1>
                <p class="page-subtitle">
                    صفوف الدفتر نفسها — مش أرقام مجمّعة. كل سطر بيرجع لصب تاسك واحدة
                    وقاعدة واحدة ولحظة واحدة، فلما حد يعترض على رقم مكافأة، ده المكان
                    اللي بيجاوب.
                    <a href="{{ route('reports.points') }}">الملخص المجمّع هنا</a>.
                </p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.points-ledger" />
            </div>
        </div>

        <form method="GET" action="{{ route('reports.points-detail') }}" class="filters">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input filters__search"
                   placeholder="دوّر برقم تذكرة أو عنوان صب تاسك أو سبب…" aria-label="بحث">

            <div class="filters__combobox">
                <x-combobox name="person" resource="users"
                            :value="$filters['person'] ?? null"
                            :selected="$selectedPerson"
                            placeholder="كل الموظفين" />
            </div>

            <select name="period" class="select filters__select" aria-label="الشهر">
                <option value="">كل الشهور</option>
                @foreach ($months as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['period'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="role" class="select filters__select" aria-label="الدور">
                <option value="">كل الأدوار</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((int) ($filters['role'] ?? 0) === $role->id)>{{ $role->name_ar }}</option>
                @endforeach
            </select>

            <select name="type" class="select filters__select" aria-label="نوع التذكرة">
                <option value="">كل الأنواع</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="kind" class="select filters__select" aria-label="نوع السطر">
                <option value="">كل السطور</option>
                <option value="award" @selected(($filters['kind'] ?? '') === 'award')>صرف تلقائي بس</option>
                {{-- ★ (2026-08-05) الخصم التلقائي على التأخير نوع تالت — سؤال
                     «مين اتخصم منه الشهر ده» بقى فلتر واحد. --}}
                <option value="penalty" @selected(($filters['kind'] ?? '') === 'penalty')>خصم تأخير بس</option>
                <option value="correction" @selected(($filters['kind'] ?? '') === 'correction')>تصحيحات يدوية بس</option>
            </select>

            <div class="filters__combobox">
                <x-combobox name="company" resource="companies"
                            :value="$filters['company'] ?? null"
                            :selected="$selectedCompany"
                            placeholder="كل الشركات" />
            </div>

            {{-- A date pair reads as one control, so it gets one bordered
                 shell rather than two loose inputs with words between them. --}}
            <div class="filters__range">
                <span class="filters__range-label">من</span>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="من تاريخ">
                <span class="filters__range-label">لـ</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="لتاريخ">
            </div>

            <x-button variant="secondary" size="sm">فلترة</x-button>

            @if (array_filter($filters))
                <a class="btn btn--ghost btn--sm" href="{{ route('reports.points-detail') }}">مسح</a>
            @endif
        </form>

        {{-- The totals describe what the filter selected, never the whole
             ledger: a total that ignores the filter above it is a trap. --}}
        <div class="today-stats">
            <div class="stat-tile stat-tile--teal">
                <div class="stat-tile__figure">{{ $n($summary->total) }}</div>
                <div class="stat-tile__caption">إجمالي النقاط في النتيجة دي</div>
            </div>

            <div class="stat-tile stat-tile--slate">
                <div class="stat-tile__figure">{{ $summary->entries }}</div>
                <div class="stat-tile__caption">سطر</div>
            </div>

            <div class="stat-tile stat-tile--green">
                <div class="stat-tile__figure">{{ $summary->people }}</div>
                <div class="stat-tile__caption">موظف</div>
            </div>
        </div>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الموظف</th>
                            <th>التذكرة</th>
                            <th>الصب تاسك</th>
                            <th>الجهة</th>
                            <th>المصدر</th>
                            <th class="table__cell--num">النقاط</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                {{-- Stored UTC, read in Cairo — same as every
                                     other timestamp in the app, and same as
                                     the export of this very screen. --}}
                                <td class="u-mono u-ltr">{{ $row->created_at->timezone(config('app.display_timezone'))->format('Y-m-d H:i') }}</td>

                                <td>
                                    <a class="row" href="{{ route('reports.employee', $row->user_id) }}">
                                        <x-avatar :user="$row->user" size="sm" />
                                        <span>{{ $row->user->name }}</span>
                                    </a>
                                </td>

                                {{-- The number alone made the reader open the
                                     ticket to find out which one it was. A row
                                     meant to settle a bonus dispute has to say
                                     what the work was. --}}
                                <td>
                                    @if ($row->ticket)
                                        <span class="tickets__number">{{ $row->ticket->ticket_number }}</span>
                                        <a class="tickets__title" href="{{ route('tickets.show', $row->ticket_id) }}">{{ $row->ticket->title }}</a>
                                        <span class="tickets__company">{{ $row->ticket->originLabel() }}</span>
                                    @else
                                        <span class="u-subtle">—</span>
                                    @endif
                                </td>

                                <td class="table__cell--muted">
                                    {{ $row->subtask?->title ?? '—' }}
                                    {{-- ★ (2026-08-05) هنا مفيش عمود «السبب» زي
                                         شاشة «نقاطي»، وسطر بالسالب من غير سبب
                                         مكتوب هو بالظبط السطر الي بيتعمل عليه
                                         خناقة. الخصم بس هو الي بيجيب سببه معاه —
                                         الصرف العادي سببه واضح من باقي الصف. --}}
                                    @if ($row->type === 'penalty' && $row->reason)
                                        <span class="tickets__company">{{ $row->reason }}</span>
                                    @endif
                                </td>

                                <td>{{ $row->side?->label() ?? $row->role?->name_ar ?? '—' }}</td>

                                {{-- Where the number came from: an automatic award,
                                     or a person who typed a correction. The second
                                     kind needs a name against it. --}}
                                <td>
                                    @if ($row->type === 'correction')
                                        <x-badge variant="amber" class="badge--sm">تصحيح يدوي</x-badge>
                                        <span class="tickets__company">{{ $row->creator?->name ?? '—' }}</span>
                                    @elseif ($row->type === 'penalty')
                                        {{-- ★ (2026-08-05) مفيش اسم جنبه زي
                                             التصحيح اليدوي: محدش «عمل» الخصم ده
                                             — تاريخ الاستحقاق هو الي عمله. --}}
                                        <x-badge variant="urgent" class="badge--sm">خصم تأخير</x-badge>
                                    @else
                                        <span class="u-subtle">صرف تلقائي</span>
                                    @endif
                                </td>

                                <td @class(['table__cell--num', 'points-cell', 'points-cell--negative' => $row->points < 0])>
                                    {{ $n($row->points) }}
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="7">
                                    {{ array_filter($filters) ? 'مفيش سطور مطابقة للفلتر.' : 'مفيش نقاط اتصرفت لسه.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $rows->links() }}
    </div>
@endsection
