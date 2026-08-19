@php
    // Trailing zeros off a points figure (1.50 -> 1.5), money kept at two.
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $m = fn ($v) => number_format((float) $v, 2);
@endphp

@include('reports.partials._month-picker', ['route' => 'admin.point-values.index'])

<div class="today-stats">
    <div class="stat-tile stat-tile--teal">
        <div class="stat-tile__figure">{{ $m($total) }}</div>
        <div class="stat-tile__caption">إجمالي مستحقات الشهر</div>
    </div>

    <div class="stat-tile stat-tile--green">
        <div class="stat-tile__figure">{{ $rows->count() }}</div>
        <div class="stat-tile__caption">موظف له مستحقات</div>
    </div>

    @if ($unpriced > 0)
        {{-- أنواع عليها شغل الشهر ده وسعرها لسه صفر — عشان إجمالي
             بصفر في شهر مليان شغل يتقرا «الأسعار لسه متحطتش»
             مش «محدش اشتغل». --}}
        <div class="stat-tile stat-tile--amber">
            <div class="stat-tile__figure">{{ $unpriced }}</div>
            <div class="stat-tile__caption">نوع عليه شغل وسعره لسه صفر</div>
        </div>
    @endif
</div>

<x-card title="المستحقات بالنوع" flush>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>النوع</th>
                    <th>سعر النقطة</th>
                    <th>النقاط</th>
                    <th>المستحق</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byType as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="u-mono">{{ $m($row['rate']) }}</td>
                        <td class="u-mono">{{ $n($row['points']) }}</td>
                        <td class="u-mono">{{ $m($row['money']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="u-subtle">مفيش نقاط في الشهر ده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>

<x-card title="المستحقات لكل شخص" flush>
    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th>التفصيل بالنوع</th>
                    <th>إجمالي النقاط</th>
                    <th>المستحق</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>
                            <ul class="rate-breakdown">
                                @foreach ($row['types'] as $t)
                                    <li>
                                        <span>{{ $t['label'] }}</span>
                                        <span class="u-mono">{{ $n($t['points']) }} × {{ $m($t['rate']) }}</span>
                                        <span class="u-mono">= {{ $m($t['money']) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </td>
                        <td class="u-mono">{{ $n($row['points']) }}</td>
                        <td class="u-mono">{{ $m($row['money']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="u-subtle">مفيش مستحقات في الشهر ده.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
