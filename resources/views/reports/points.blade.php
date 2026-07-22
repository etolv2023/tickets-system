@extends('layouts.app')

@section('title', 'تقرير النقاط')

@php
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $delta = $previous > 0 ? round((($total - $previous) / $previous) * 100) : null;
@endphp

@section('content')
    {{-- ★★★★ (2026-07-21): paired report-card grids, same case as /reports. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">تقرير النقاط</h1>
                <p class="page-subtitle">
                    كل نقطة مصدرها صب تاسك واحد خلص — القيمة بتتحط بنقطة واحدة
                    افتراضياً وقت إنشاء الصب تاسك، وتقدر تتعدّل لكل صب تاسك لوحده.
                    مفيش تقسيم ومفيش نقاط على مستوى التذكرة نفسها. التصحيحات اليدوية
                    (لو فيه) محسوبة في الإجمالي وبتتمّيز في جدول كل موظف.
                    تسجيل الوقت مالوش أي علاقة بيها.
                </p>
            </div>
        </div>

        @include('reports.partials._month-picker', ['route' => 'reports.points'])

        {{-- The headline. Four numbers that frame everything under them. --}}
        <div class="today-stats">
            <div class="stat-tile stat-tile--teal">
                <div class="stat-tile__figure">{{ $n($total) }}</div>
                <div class="stat-tile__caption">
                    إجمالي نقاط الشهر
                    @if ($delta !== null)
                        <span @class(['points-delta', 'points-delta--up' => $delta >= 0])>
                            {{ $delta >= 0 ? '▲' : '▼' }} {{ abs($delta) }}٪ عن الشهر الي فات
                        </span>
                    @endif
                </div>
            </div>

            <div class="stat-tile stat-tile--green">
                <div class="stat-tile__figure">{{ $people }}</div>
                <div class="stat-tile__caption">موظف أخد نقاط</div>
            </div>

            <div class="stat-tile stat-tile--amber">
                <div class="stat-tile__figure">{{ $tickets }}</div>
                <div class="stat-tile__caption">تذكرة اتصرفت عليها نقاط</div>
            </div>

            <div class="stat-tile stat-tile--slate">
                <div class="stat-tile__figure">{{ $tickets ? $n($total / $tickets) : '—' }}</div>
                <div class="stat-tile__caption">متوسط نقاط التذكرة</div>
            </div>

            @if ($correctionsCount > 0)
                <div class="stat-tile">
                    <div class="stat-tile__figure">{{ $n($correctionsTotal) }}</div>
                    <div class="stat-tile__caption">
                        تصحيحات يدوية ({{ $correctionsCount }})
                        <a href="{{ route('admin.point-rules.index') }}">التفاصيل</a>
                    </div>
                </div>
            @endif
        </div>

        <x-collapsible-section title="النقاط لكل موظف" :meta="$byPerson->count() . ' موظف'">
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>الموظف</th>
                            <th class="table__cell--num">دعم</th>
                            <th class="table__cell--num">فرونت</th>
                            <th class="table__cell--num">باك</th>
                            <th class="table__cell--num">تيست</th>
                            <th class="table__cell--num">ديف أوبس</th>
                            <th class="table__cell--num">تذاكر</th>
                            <th class="table__cell--num">الإجمالي</th>
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
                                            <small class="u-subtle">{{ $row->user->role?->name_ar }}</small>
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
                            <tr class="table__empty"><td colspan="8">مفيش نقاط اتصرفت في الشهر ده.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-collapsible-section>

        <x-collapsible-section title="النقاط حسب نوع التذكرة"
                               meta="من أنهي نوع جت النقط">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th class="table__cell--num">تذاكر</th>
                            <th class="table__cell--num">صفوف نقاط</th>
                            <th class="table__cell--num">الإجمالي</th>
                            <th class="table__cell--num">متوسط التذكرة</th>
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
                            <tr class="table__empty"><td colspan="5">مفيش بيانات.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-collapsible-section>

        <x-collapsible-section title="النقاط حسب الجهة" :open="false">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الجهة</th>
                            <th class="table__cell--num">صفوف</th>
                            <th class="table__cell--num">الإجمالي</th>
                            <th class="table__cell--num">النسبة</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($bySide as $row)
                            <tr>
                                <td>{{ $row->side?->label() ?? $row->role?->name_ar ?? '—' }}</td>
                                <td class="table__cell--num u-subtle">{{ $row->awards }}</td>
                                <td class="table__cell--num points-cell">{{ $n($row->total) }}</td>
                                <td class="table__cell--num">
                                    {{ $total > 0 ? round(($row->total / $total) * 100) . '٪' : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty"><td colspan="4">مفيش بيانات.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-collapsible-section>

        <x-collapsible-section title="أعلى 10 تذاكر بالنقاط" :open="false">
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>التذكرة</th>
                            <th>الجهة الطالبة</th>
                            <th class="table__cell--num">مشاركين</th>
                            <th class="table__cell--num">النقاط</th>
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
                            <tr class="table__empty"><td colspan="4">مفيش بيانات.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-collapsible-section>
    </div>
@endsection
