<x-card title="أداء الشركات" flush>
    <x-slot:actions>
        <span class="u-subtle">مين بيبعت أكتر</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th>الشركة</th>
                    <th class="table__cell--num">التذاكر</th>
                    <th class="table__cell--num">اتحلت</th>
                    <th class="table__cell--num">متوسط زمن الحل</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $row)
                    <tr>
                        {{-- The internal-work row has no company_id, so it gets
                             a label rather than a link to a customer page that
                             does not exist. F25 --}}
                        <td>
                            @if ($row->company_id === null)
                                <span class="u-muted">شغل داخلي</span>
                            @elsecan('companies.manage')
                                <a href="{{ route('admin.companies.show', $row->company_id) }}">{{ $row->company?->name ?? '—' }}</a>
                            @else
                                {{ $row->company?->name ?? '—' }}
                            @endcan
                        </td>
                        <td class="table__cell--num">{{ $row->total }}</td>
                        <td class="table__cell--num">{{ $row->resolved }}</td>
                        <td class="table__cell--num">{{ $row->avg_hours ? round($row->avg_hours) . ' س' : '—' }}</td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="4">مفيش بيانات في الفترة دي.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
