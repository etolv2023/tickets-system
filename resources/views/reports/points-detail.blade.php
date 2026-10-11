@extends('layouts.app')

@section('title', __('تقرير النقاط التفصيلي'))

@php
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

@section('content')
    {{-- ★★★★ (2026-07-21): paired report-card grids, same case as /reports. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('تقرير النقاط التفصيلي') }}</h1>
                <p class="page-subtitle">
                    {{ __('صفوف الدفتر نفسها — وليست أرقامًا مجمّعة. يرجع كل سطر إلى مهمة فرعية واحدة وقاعدة واحدة ولحظة واحدة، فعند الاعتراض على رقم مكافأة تجد الإجابة هنا.') }}
                    <a href="{{ route('reports.points') }}">{{ __('الملخص المجمّع هنا') }}</a>.
                </p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.points-ledger" />
            </div>
        </div>

        <form method="GET" action="{{ route('reports.points-detail') }}"
              class="filters reports-ledger-filters" x-data="{ filtersOpen: false }">
            <div class="filters__bar">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input filters__search"
                       placeholder="{{ __('ابحث برقم تذكرة أو عنوان مهمة فرعية أو سبب…') }}" aria-label="{{ __('بحث') }}">
                <button type="button" class="btn btn--secondary" @click="filtersOpen = !filtersOpen"
                        :aria-expanded="filtersOpen.toString()">
                    <x-icon name="filter" class="btn__icon" /> {{ __('عوامل التصفية') }}
                </button>
                <x-button variant="secondary" size="sm">{{ __('تصفية') }}</x-button>

                @if (array_filter($filters))
                    <a class="btn btn--ghost btn--sm" href="{{ route('reports.points-detail') }}">{{ __('مسح') }}</a>
                @endif
            </div>

            <div class="reports-ledger-filters__panel" x-show="filtersOpen" x-cloak>

            <div class="filters__combobox">
                <x-combobox name="person" resource="users"
                            :value="$filters['person'] ?? null"
                            :selected="$selectedPerson"
                            :placeholder="__('جميع الموظفين')" />
            </div>

            <select name="period" class="select filters__select" aria-label="{{ __('الشهر') }}">
                <option value="">{{ __('كل الشهور') }}</option>
                @foreach ($months as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['period'] ?? '') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>

            <select name="role" class="select filters__select" aria-label="{{ __('الدور') }}">
                <option value="">{{ __('جميع الأدوار') }}</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected((int) ($filters['role'] ?? 0) === $role->id)>{{ $role->label() }}</option>
                @endforeach
            </select>

            <select name="type" class="select filters__select" aria-label="{{ __('نوع التذكرة') }}">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>

            <select name="kind" class="select filters__select" aria-label="{{ __('نوع السطر') }}">
                <option value="">{{ __('جميع السطور') }}</option>
                <option value="award" @selected(($filters['kind'] ?? '') === 'award')>{{ __('الاحتساب التلقائي فقط') }}</option>
                {{-- ★ (2026-08-05) الخصم التلقائي على التأخير نوع تالت — سؤال
                     «مين اتخصم منه الشهر ده» بقى فلتر واحد. --}}
                <option value="penalty" @selected(($filters['kind'] ?? '') === 'penalty')>{{ __('خصومات التأخير فقط') }}</option>
                <option value="correction" @selected(($filters['kind'] ?? '') === 'correction')>{{ __('التصحيحات اليدوية فقط') }}</option>
            </select>

            <div class="filters__combobox">
                <x-combobox name="company" resource="companies"
                            :value="$filters['company'] ?? null"
                            :selected="$selectedCompany"
                            :placeholder="__('جميع الشركات')" />
            </div>

            {{-- A date pair reads as one control, so it gets one bordered
                 shell rather than two loose inputs with words between them. --}}
            <div class="filters__range">
                <span class="filters__range-label">{{ __('من') }}</span>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="{{ __('من تاريخ') }}">
                <span class="filters__range-label">{{ __('إلى') }}</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="{{ __('حتى تاريخ') }}">
            </div>

            </div>
        </form>

        {{-- The totals describe what the filter selected, never the whole
             ledger: a total that ignores the filter above it is a trap. --}}
        <div class="today-stats">
            <div class="stat-tile stat-tile--teal">
                <div class="stat-tile__figure">{{ $n($summary->total) }}</div>
                <div class="stat-tile__caption">{{ __('إجمالي النقاط في هذه النتيجة') }}</div>
            </div>

            <div class="stat-tile stat-tile--slate">
                <div class="stat-tile__figure">{{ $summary->entries }}</div>
                <div class="stat-tile__caption">{{ __('سطر') }}</div>
            </div>

            <div class="stat-tile stat-tile--green">
                <div class="stat-tile__figure">{{ $summary->people }}</div>
                <div class="stat-tile__caption">{{ __('الموظف') }}</div>
            </div>
        </div>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>{{ __('التاريخ') }}</th>
                            <th>{{ __('الموظف') }}</th>
                            <th>{{ __('التذكرة') }}</th>
                            <th>{{ __('المهمة الفرعية') }}</th>
                            <th>{{ __('الجهة') }}</th>
                            <th>{{ __('المصدر') }}</th>
                            <th class="table__cell--num">{{ __('النقاط') }}</th>
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

                                <td>{{ $row->side?->label() ?? $row->role?->label() ?? '—' }}</td>

                                {{-- Where the number came from: an automatic award,
                                     or a person who typed a correction. The second
                                     kind needs a name against it. --}}
                                <td>
                                    @if ($row->type === 'correction')
                                        <x-badge variant="amber" class="badge--sm">{{ __('تصحيح يدوي') }}</x-badge>
                                        <span class="tickets__company">{{ $row->creator?->name ?? '—' }}</span>
                                    @elseif ($row->type === 'penalty')
                                        {{-- ★ (2026-08-05) مفيش اسم جنبه زي
                                             التصحيح اليدوي: محدش «عمل» الخصم ده
                                             — تاريخ الاستحقاق هو الي عمله. --}}
                                        <x-badge variant="urgent" class="badge--sm">{{ __('خصم تأخير') }}</x-badge>
                                    @else
                                        <span class="u-subtle">{{ __('احتساب تلقائي') }}</span>
                                    @endif
                                </td>

                                <td @class(['table__cell--num', 'points-cell', 'points-cell--negative' => $row->points < 0])>
                                    {{ $n($row->points) }}
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="7">
                                    {{ array_filter($filters) ? __('لا توجد سطور مطابقة لعامل التصفية.') : __('لم تُحتسب أي نقاط بعد.') }}
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
