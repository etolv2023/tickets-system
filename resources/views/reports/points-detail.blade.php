@extends('layouts.app')

@section('title', 'تقرير النقاط التفصيلي')

@php
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
@endphp

@section('content')
    <div class="page">
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

            <select name="side" class="select filters__select" aria-label="الجهة">
                <option value="">كل الجهات</option>
                @foreach ($sides as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['side'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="type" class="select filters__select" aria-label="نوع التذكرة">
                <option value="">كل الأنواع</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="kind" class="select filters__select" aria-label="نوع السطر">
                <option value="">صرف وتصحيحات</option>
                <option value="award" @selected(($filters['kind'] ?? '') === 'award')>صرف تلقائي بس</option>
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
                                <td class="u-mono u-ltr">{{ $row->created_at->format('Y-m-d H:i') }}</td>

                                <td>
                                    <a class="row" href="{{ route('reports.employee', $row->user_id) }}">
                                        <x-avatar :user="$row->user" size="sm" />
                                        <span>{{ $row->user->name }}</span>
                                    </a>
                                </td>

                                <td>
                                    @if ($row->ticket)
                                        <a href="{{ route('tickets.show', $row->ticket_id) }}">
                                            <span class="u-mono u-ltr">{{ $row->ticket->ticket_number }}</span>
                                            <span class="tickets__company">{{ $row->ticket->originLabel() }}</span>
                                        </a>
                                    @else
                                        <span class="u-subtle">—</span>
                                    @endif
                                </td>

                                <td class="table__cell--muted">
                                    {{ $row->subtask?->title ?? '—' }}
                                </td>

                                <td>{{ $row->side->label() }}</td>

                                {{-- Where the number came from: a matrix rule, or a
                                     person who typed it. The second kind needs a name
                                     against it. --}}
                                <td>
                                    @if ($row->type === 'correction')
                                        <x-badge variant="amber" class="badge--sm">تصحيح يدوي</x-badge>
                                        <span class="tickets__company">{{ $row->creator?->name ?? '—' }}</span>
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
