{{-- ★★★★★ (2026-07-21) Per-type totals with a completion meter. The aggregation
     (total / done / open / pct) is done in DashboardService::ticketStatsByType,
     never here (CLAUDE.md § 3). The meter is a bar filled to the done ratio in
     green — a meter, not a chart — so "how much of this type is closed" reads at
     a glance beside the exact counts. --}}
<x-card title="إحصائيات التذاكر" flush>
    <x-slot:actions>
        <span class="u-subtle">الشهر ده</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>النوع</th>
                    <th class="table__cell--num">الإجمالي</th>
                    <th class="table__cell--num">محلولة</th>
                    <th class="table__cell--num">مفتوحة</th>
                    <th>الإنجاز</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ticketStats as $row)
                    <tr>
                        <td><x-badge :variant="$row->type->variant()" :icon="$row->type->icon()">{{ $row->type->label() }}</x-badge></td>
                        <td class="table__cell--num">{{ $row->total }}</td>
                        <td class="table__cell--num">{{ $row->done }}</td>
                        <td class="table__cell--num">{{ $row->open }}</td>
                        <td>
                            <div class="meter meter--green" title="{{ $row->pct }}%">
                                <div class="meter__fill" style="--value: {{ $row->pct }}%"></div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="today__clear">
                                <x-icon name="check-circle" class="today__clear-glyph" />
                                مفيش تذاكر اتفتحت الشهر ده.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
