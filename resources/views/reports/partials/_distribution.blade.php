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
                @forelse ($distribution as $row)
                    <tr>
                        <td><x-badge :variant="$row->type->variant()">{{ $row->type->label() }}</x-badge></td>
                        <td class="table__cell--num">{{ $row->total }}</td>
                        <td class="table__cell--num">{{ $row->done }}</td>
                        <td class="table__cell--num">{{ $row->open }}</td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="4">مفيش تذاكر في الفترة دي.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
