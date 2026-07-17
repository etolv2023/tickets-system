@php
    // A table, not a pie chart — § 6 rules charts out, and five numbers read
    // faster in a column than in a donut anyway.
    $byType = $distribution->groupBy('type');
@endphp

<x-card title="توزيع التذاكر" flush>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>النوع</th>
                    <th class="table__cell--num">الإجمالي</th>
                    <th class="table__cell--num">محلولة</th>
                    <th class="table__cell--num">مفتوحة</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byType as $type => $rows)
                    @php
                        $enum = \App\Enums\TicketType::from($type);
                        $total = $rows->sum('n');
                        $done = $rows->whereIn('status', ['resolved', 'closed'])->sum('n');
                    @endphp
                    <tr>
                        <td><x-badge :variant="$enum->variant()">{{ $enum->label() }}</x-badge></td>
                        <td class="table__cell--num">{{ $total }}</td>
                        <td class="table__cell--num">{{ $done }}</td>
                        <td class="table__cell--num">{{ $total - $done }}</td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="4">مفيش تذاكر في الفترة دي.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
