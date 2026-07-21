@extends('layouts.app')

@section('title', 'نقاطي')

@php
    $awardsCount = $transactions->where('type', '!=', 'correction')->count();
    $correctionsTotal = (float) $transactions->where('type', 'correction')->sum('points');
    $lastEarned = $transactions->first()?->created_at;
@endphp

@section('content')
    {{-- ★★★★★ (2026-07-21): a proper hero for the number people come here to
         see, same stat-tile component the home page and points-report already
         use — not a new pattern, just this screen's first real use of it. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title row">
                    <x-icon name="star" class="u-icon-accent" />
                    نقاطي
                </h1>
                <p class="page-subtitle">النقاط بتتصرف مرة واحدة أول ما التذكرة تتحل.</p>
            </div>
        </div>

        <div class="today-stats">
            <div class="stat-tile stat-tile--teal">
                <div class="stat-tile__figure">{{ rtrim(rtrim(number_format($total, 2), '0'), '.') }}</div>
                <div class="stat-tile__caption">{{ $isAll ? 'إجمالي كل الأوقات' : 'نقاط الشهر ده' }}</div>
            </div>

            <div class="stat-tile stat-tile--slate">
                <div class="stat-tile__figure">{{ $awardsCount }}</div>
                <div class="stat-tile__caption">مرة اتصرفلك فيها نقط</div>
            </div>

            @if ($lastEarned)
                <div class="stat-tile stat-tile--green">
                    <div class="stat-tile__figure u-mono">{{ $lastEarned->format('j M') }}</div>
                    <div class="stat-tile__caption">آخر نقطة اتصرفت</div>
                </div>
            @endif

            @if ($correctionsTotal !== 0.0)
                <div @class(['stat-tile', $correctionsTotal > 0 ? 'stat-tile--green' : 'stat-tile--red'])>
                    <div class="stat-tile__figure">{{ rtrim(rtrim(number_format($correctionsTotal, 2), '0'), '.') }}</div>
                    <div class="stat-tile__caption">تصحيحات يدوية</div>
                </div>
            @endif
        </div>

        <div class="row row--wrap row--between">
            @include('reports.partials._month-picker', ['route' => 'reports.my-points'])

            @if ($isAll)
                <a class="btn btn--ghost btn--sm" href="{{ route('reports.my-points') }}">
                    ارجع للشهر الحالي
                </a>
            @else
                <a class="btn btn--ghost btn--sm" href="{{ route('reports.my-points', ['period' => 'all']) }}">
                    <x-icon name="clock" class="btn__icon" />
                    كل النقاط
                </a>
            @endif
        </div>

        @if ($isAll)
            <x-alert>بتشوف كل النقاط اللي اتصرفتلك من أول يوم — مش الشهر الحالي بس.</x-alert>
        @endif

        <x-card flush>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>المصدر</th>
                            <th>الصب تاسك</th>
                            <th>الجهة</th>
                            <th>السبب</th>
                            <th class="table__cell--num">النقاط</th>
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
                                        <x-badge variant="neutral">تصحيح يدوي</x-badge>
                                    @else
                                        {{ $row->subtask->title ?? '—' }}
                                    @endif
                                </td>
                                <td>{{ $row->side->label() }}</td>
                                <td class="u-subtle">{{ $row->reason }}</td>
                                <td class="table__cell--num">{{ rtrim(rtrim($row->points, '0'), '.') }}</td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="5">{{ $isAll ? 'مأخدتش نقاط لسه خالص.' : 'مأخدتش نقاط في الشهر ده.' }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <p class="field__hint">
            تسجيل الوقت مالوش علاقة بالنقاط — النقاط على إنجاز التذكرة مش على الساعات.
        </p>
    </div>
@endsection
