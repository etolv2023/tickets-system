@extends('layouts.app')

@section('title', __('تقرير النقاط'))

@php
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $delta = $previous > 0 ? round((($total - $previous) / $previous) * 100) : null;
@endphp

@section('content')
    {{-- ★★★★ (2026-07-21): paired report-card grids, same case as /reports. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('تقرير النقاط') }}</h1>
                <p class="page-subtitle">
                    {{ __('مصدر كل نقطة مهمة فرعية واحدة مكتملة — تبدأ القيمة بنقطة واحدة افتراضيًا عند إنشاء المهمة الفرعية، ويمكن تعديلها لكل مهمة على حدة. لا يوجد تقسيم ولا نقاط على مستوى التذكرة نفسها. التصحيحات اليدوية (إن وجدت) محسوبة في الإجمالي ومميزة في جدول كل موظف. ولا علاقة لتسجيل الوقت بها.') }}
                </p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.points-summary" />
            </div>
        </div>

        {{-- ★ (2026-08-19) الفلتر بالنوع جنب فلتر الشهر — فورم واحدة
             عشان اختيار أي واحد فيهم ميضيّعش التاني. كل رقم في الصفحة
             بيتفلتر مع بعض (ReportService::pointsReport)، غير جدول
             «النقاط بالنوع» نفسه — ده الخريطة اللي بتوريك إنت فين. --}}
        <form method="GET" action="{{ route('reports.points') }}" class="filters">
            <select name="period" class="select filters__select" onchange="this.form.submit()">
                @foreach ($months as $value => $label)
                    <option value="{{ $value }}" @selected($period === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            <select name="type" class="select filters__select" onchange="this.form.submit()"
                    aria-label="{{ __('نوع التذكرة') }}">
                <option value="">{{ __('كل الأنواع') }}</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(($type ?? '') === $value)>{{ __($label) }}</option>
                @endforeach
            </select>
            <noscript><x-button variant="secondary">{{ __('عرض') }}</x-button></noscript>
        </form>

        @if ($type)
            <x-alert variant="info">
                {{ __('هذه الأرقام للنوع «:type» فقط.', ['type' => $types[$type]]) }}
                <a href="{{ route('reports.points', ['period' => $period]) }}">{{ __('عرض جميع الأنواع') }}</a>
            </x-alert>
        @endif

        {{-- The headline. Four numbers that frame everything under them. --}}
        <div class="today-stats">
            <div class="stat-tile stat-tile--teal">
                <div class="stat-tile__figure">{{ $n($total) }}</div>
                <div class="stat-tile__caption">
                    {{ __('إجمالي نقاط الشهر') }}
                    @if ($delta !== null)
                        <span @class(['points-delta', 'points-delta--up' => $delta >= 0])>
                            {{ $delta >= 0 ? '▲' : '▼' }} {{ __(':percent٪ مقارنة بالشهر الماضي', ['percent' => abs($delta)]) }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="stat-tile stat-tile--green">
                <div class="stat-tile__figure">{{ $people }}</div>
                <div class="stat-tile__caption">{{ __('موظفون حصلوا على نقاط') }}</div>
            </div>

            <div class="stat-tile stat-tile--amber">
                <div class="stat-tile__figure">{{ $tickets }}</div>
                <div class="stat-tile__caption">{{ __('تذاكر احتُسبت عليها نقاط') }}</div>
            </div>

            <div class="stat-tile stat-tile--slate">
                <div class="stat-tile__figure">{{ $tickets ? $n($total / $tickets) : '—' }}</div>
                <div class="stat-tile__caption">{{ __('متوسط نقاط التذكرة') }}</div>
            </div>

            @if ($correctionsCount > 0)
                <div class="stat-tile">
                    <div class="stat-tile__figure">{{ $n($correctionsTotal) }}</div>
                    <div class="stat-tile__caption">
                        {{ __('تصحيحات يدوية (:count)', ['count' => $correctionsCount]) }}
                        <a href="{{ route('admin.point-rules.index') }}">{{ __('التفاصيل') }}</a>
                    </div>
                </div>
            @endif
        </div>

        <x-collapsible-section :title="__('النقاط لكل موظف')" :meta="trans_choice(':count موظف|موظف واحد|موظفان|:count موظفين|:count موظفًا|:count موظف', $byPerson->count())">
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>{{ __('الموظف') }}</th>
                            <th class="table__cell--num">{{ __('الدعم') }}</th>
                            <th class="table__cell--num">{{ __('الواجهة الأمامية') }}</th>
                            <th class="table__cell--num">{{ __('الأنظمة الخلفية') }}</th>
                            <th class="table__cell--num">{{ __('الاختبار') }}</th>
                            <th class="table__cell--num">{{ __('عمليات التطوير') }}</th>
                            <th class="table__cell--num">{{ __('تذاكر') }}</th>
                            <th class="table__cell--num">{{ __('الإجمالي') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byPerson as $row)
                            <tr>
                                <td>
                                    <a class="row" href="{{ route('reports.employee', $row->user_id) }}">
                                        <x-avatar :user="$row->user" size="sm" />
                                        <span>
                                            {{ $row->user->name }}
                                            <small class="u-subtle">{{ $row->user->role?->label() }}</small>
                                        </span>
                                    </a>
                                </td>
                                <td class="table__cell--num">{{ $row->support > 0 ? $n($row->support) : '—' }}</td>
                                <td class="table__cell--num">{{ $row->frontend > 0 ? $n($row->frontend) : '—' }}</td>
                                <td class="table__cell--num">{{ $row->backend > 0 ? $n($row->backend) : '—' }}</td>
                                <td class="table__cell--num">{{ $row->tester > 0 ? $n($row->tester) : '—' }}</td>
                                <td class="table__cell--num">{{ $row->devops > 0 ? $n($row->devops) : '—' }}</td>
                                <td class="table__cell--num u-subtle">{{ $row->awards }}</td>
                                <td class="table__cell--num points-cell">{{ $n($row->total) }}</td>
                            </tr>
                        @empty
                            <tr class="table__empty"><td colspan="8">{{ __('لم تُحتسب نقاط هذا الشهر.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-collapsible-section>

        <x-collapsible-section :title="__('النقاط حسب نوع التذكرة')"
                               :meta="__('مصدر النقاط حسب النوع')">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('النوع') }}</th>
                            <th class="table__cell--num">{{ __('تذاكر') }}</th>
                            <th class="table__cell--num">{{ __('صفوف نقاط') }}</th>
                            <th class="table__cell--num">{{ __('الإجمالي') }}</th>
                            <th class="table__cell--num">{{ __('متوسط التذكرة') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($byType as $row)
                            @php $type = \App\Casts\TicketTypeValue::for($row->type); @endphp
                            <tr>
                                <td><x-badge :variant="$type->variant()" :icon="$type->icon()">{{ $type->label() }}</x-badge></td>
                                <td class="table__cell--num">{{ $row->tickets }}</td>
                                <td class="table__cell--num u-subtle">{{ $row->awards }}</td>
                                <td class="table__cell--num points-cell">{{ $n($row->total) }}</td>
                                <td class="table__cell--num">{{ $n($row->total / max(1, $row->tickets)) }}</td>
                            </tr>
                        @empty
                            <tr class="table__empty"><td colspan="5">{{ __('لا توجد بيانات.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-collapsible-section>

        <x-collapsible-section :title="__('النقاط حسب الجهة')" :open="false">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('الجهة') }}</th>
                            <th class="table__cell--num">{{ __('صفوف') }}</th>
                            <th class="table__cell--num">{{ __('الإجمالي') }}</th>
                            <th class="table__cell--num">{{ __('النسبة') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bySide as $row)
                            <tr>
                                <td>{{ $row->side?->label() ?? $row->role?->label() ?? '—' }}</td>
                                <td class="table__cell--num u-subtle">{{ $row->awards }}</td>
                                <td class="table__cell--num points-cell">{{ $n($row->total) }}</td>
                                <td class="table__cell--num">
                                    {{ $total > 0 ? __(':percent٪', ['percent' => round(($row->total / $total) * 100)]) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty"><td colspan="4">{{ __('لا توجد بيانات.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-collapsible-section>

        <x-collapsible-section :title="__('أعلى 10 تذاكر بالنقاط')" :open="false">
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>{{ __('التذكرة') }}</th>
                            <th>{{ __('الجهة الطالبة') }}</th>
                            <th class="table__cell--num">{{ __('المشاركون') }}</th>
                            <th class="table__cell--num">{{ __('النقاط') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topTickets as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('tickets.show', $row->ticket_id) }}">
                                        <span class="u-mono u-ltr">{{ $row->ticket->ticket_number }}</span>
                                        {{ Str::limit($row->ticket->title, 45) }}
                                    </a>
                                </td>
                                <td class="u-muted">{{ $row->ticket->originLabel() }}</td>
                                <td class="table__cell--num u-subtle">{{ $row->people }}</td>
                                <td class="table__cell--num points-cell">{{ $n($row->total) }}</td>
                            </tr>
                        @empty
                            <tr class="table__empty"><td colspan="4">{{ __('لا توجد بيانات.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-collapsible-section>
    </div>
@endsection
