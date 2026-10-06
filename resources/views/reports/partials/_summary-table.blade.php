<x-card title="التفاصيل" flush>
    <div class="table-wrap">
        <table class="table table--hover reports-table">
            <thead><tr><th>البند</th><th>الإجمالي</th><th>مفتوحة</th><th>محلولة</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->name_ar }}</td>
                        <td class="u-nums">@if (($by !== 'module' || $row->key !== '—') && (empty($filters[$by]) || $filters[$by] === $row->key))<a href="{{ route('tickets.index', array_merge($drilldown, [$by => $row->key])) }}">{{ $row->total }}</a>@else{{ $row->total }}@endif</td>
                        <td class="u-nums">
                            @if ($row->open_count > 0 && ($by !== 'module' || $row->key !== '—') && (empty($filters[$by]) || $filters[$by] === $row->key))
                                <a href="{{ route('tickets.index', array_merge($drilldown, [$by => $row->key, 'statuses' => $by === 'status' ? [$row->key] : $openStatusKeys])) }}">{{ $row->open_count }}</a>
                            @else{{ $row->open_count }}@endif
                        </td>
                        <td class="u-nums">
                            @if ($row->resolved_count > 0 && ($by !== 'module' || $row->key !== '—') && (empty($filters[$by]) || $filters[$by] === $row->key))
                                <a href="{{ route('tickets.index', array_merge($drilldown, [$by => $row->key, 'statuses' => $by === 'status' ? [$row->key] : $resolvedStatusKeys])) }}">{{ $row->resolved_count }}</a>
                            @else{{ $row->resolved_count }}@endif
                        </td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="4">مفيش بيانات بالفلاتر دي.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
