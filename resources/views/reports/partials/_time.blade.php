<x-card title="تقرير الوقت" flush>
    <x-slot:actions>
        <span class="u-subtle">الوقت مالوش علاقة بالنقاط</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>الموظف</th>
                    <th class="table__cell--num">ساعات مسجّلة</th>
                    <th class="table__cell--num">تذاكر</th>
                    <th class="table__cell--num">دقة التقدير</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($time as $row)
                    <tr>
                        <td>
                            <div class="row">
                                <x-avatar :user="$row->user" size="sm" />
                                {{ $row->user?->name ?? '—' }}
                            </div>
                        </td>
                        <td class="table__cell--num">{{ rtrim(rtrim(number_format($row->logged, 2), '0'), '.') }}</td>
                        <td class="table__cell--num">{{ $row->tickets }}</td>
                        <td class="table__cell--num">{{ $row->accuracy ?? '—' }}</td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="4">مفيش وقت مسجّل في الفترة دي.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
