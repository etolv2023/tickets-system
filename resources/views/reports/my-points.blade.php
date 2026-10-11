@extends('layouts.app')

@section('title', __('نقاطي'))

@php
    // ★ (2026-08-05) 'award' explicitly, not "anything that isn't a
    // correction": a penalty row is neither, and counting it under «مرة
    // اتصرفلك فيها نقط» would tell somebody they were paid on the exact
    // occasion they were docked.
    $awardsCount = $transactions->where('type', 'award')->count();
    $correctionsTotal = (float) $transactions->where('type', 'correction')->sum('points');
    $penalties = $transactions->where('type', 'penalty');
    $penaltiesTotal = (float) $penalties->sum('points');
    $lastEarned = $transactions->first()?->created_at;
@endphp

@section('content')
    <x-points-guide />
    {{-- ★★★★★ (2026-07-21): a proper hero for the number people come here to
         see, same stat-tile component the home page and points-report already
         use — not a new pattern, just this screen's first real use of it. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title row">
                    <x-icon name="star" class="u-icon-accent" />
                    {{ __('نقاطي') }}
                </h1>
                <p class="page-subtitle">{{ __('تُحتسب النقاط مرة واحدة عند اعتماد الحل، وتظهر الخصومات والتصحيحات كسجلات مستقلة قابلة للمراجعة.') }}</p>
            </div>
            <div class="page__actions">
                <x-export-button route="export.my-points" />
            </div>
        </div>

        <div class="today-stats">
            <div class="stat-tile stat-tile--teal">
                <div class="stat-tile__figure">{{ rtrim(rtrim(number_format($total, 2), '0'), '.') }}</div>
                <div class="stat-tile__caption">{{ $isAll ? __('إجمالي كل الأوقات') : __('نقاط هذا الشهر') }}</div>
            </div>

            <div class="stat-tile stat-tile--slate">
                <div class="stat-tile__figure">{{ $awardsCount }}</div>
                <div class="stat-tile__caption">{{ __('عمليات احتساب النقاط') }}</div>
            </div>

            @if ($lastEarned)
                <div class="stat-tile stat-tile--green">
                    <div class="stat-tile__figure u-mono">{{ $lastEarned->translatedFormat('j M') }}</div>
                    <div class="stat-tile__caption">{{ __('آخر عملية احتساب') }}</div>
                </div>
            @endif

            {{-- ★ (2026-08-05) الخصم بيتعرض لوحده مش مدفون في الإجمالي — لو
                 اتخصم منك، تعرف قد إيه وعلى كام صب تاسك من غير ما تعد
                 الجدول بإيدك. --}}
            @if ($penaltiesTotal !== 0.0)
                <div class="stat-tile stat-tile--red">
                    <div class="stat-tile__figure">{{ rtrim(rtrim(number_format($penaltiesTotal, 2), '0'), '.') }}</div>
                    <div class="stat-tile__caption">{{ __('خصم التأخير (:count مهام فرعية)', ['count' => $penalties->count()]) }}</div>
                </div>
            @endif

            @if ($correctionsTotal !== 0.0)
                <div @class(['stat-tile', $correctionsTotal > 0 ? 'stat-tile--green' : 'stat-tile--red'])>
                    <div class="stat-tile__figure">{{ rtrim(rtrim(number_format($correctionsTotal, 2), '0'), '.') }}</div>
                    <div class="stat-tile__caption">{{ __('تصحيحات يدوية') }}</div>
                </div>
            @endif
        </div>

        <div class="row row--wrap row--between">
            @include('reports.partials._month-picker', ['route' => 'reports.my-points'])

            @if ($isAll)
                <a class="btn btn--ghost btn--sm" href="{{ route('reports.my-points') }}">
                    {{ __('العودة إلى الشهر الحالي') }}
                </a>
            @else
                <a class="btn btn--ghost btn--sm" href="{{ route('reports.my-points', ['period' => 'all']) }}">
                    <x-icon name="clock" class="btn__icon" />
                    {{ __('عرض السجل الكامل') }}
                </a>
            @endif
        </div>

        @if ($isAll)
            <x-alert>{{ __('يعرض هذا النطاق سجل النقاط بالكامل منذ أول عملية، وليس الشهر الحالي فقط.') }}</x-alert>
        @endif

        <x-card flush>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('المصدر') }}</th>
                            <th>{{ __('المهمة الفرعية') }}</th>
                            <th>{{ __('الجهة') }}</th>
                            <th>{{ __('السبب') }}</th>
                            <th class="table__cell--num">{{ __('النقاط') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $row)
                            <tr>
                                <td>
                                    @if ($row->ticket)
                                        <a href="{{ route('tickets.show', $row->ticket_id) }}">
                                            <span class="u-mono u-ltr">{{ $row->ticket->ticket_number }}</span>
                                            {{ Str::limit($row->ticket->title, 40) }}
                                        </a>
                                        <x-badge :variant="$row->ticket->type->variant()" :icon="$row->ticket->type->icon()">{{ $row->ticket->type->label() }}</x-badge>
                                    @else
                                        <span class="u-subtle">—</span>
                                    @endif
                                </td>
                                <td class="u-subtle">
                                    @if ($row->type === 'correction')
                                        <x-badge variant="neutral">{{ __('تصحيح يدوي') }}</x-badge>
                                    @else
                                        {{-- ★ (2026-08-05) الخصم لسه بيشاور على
                                             الصب تاسك بتاعته — العنوان هو الي
                                             بيخلي السطر مفهوم، والبادج بيقول
                                             ليه بالسالب. --}}
                                        @if ($row->type === 'penalty')
                                            <x-badge variant="urgent">{{ __('خصم تأخير') }}</x-badge>
                                        @endif
                                        {{ $row->subtask->title ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ $row->side?->label() ?? $row->role?->label() ?? '—' }}</td>
                                <td class="u-subtle">{{ $row->reasonText() }}</td>
                                <td @class(['table__cell--num', 'points-cell--negative' => $row->points < 0])>
                                    {{ rtrim(rtrim($row->points, '0'), '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="5">{{ $isAll ? __('لم تحصل على أي نقاط بعد.') : __('لم تحصل على نقاط هذا الشهر.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <p class="field__hint">
            {{ __('تسجيل الوقت لا علاقة له بالنقاط — تُحتسب النقاط على إنجاز التذكرة لا على الساعات.') }}
        </p>
    </div>
@endsection
