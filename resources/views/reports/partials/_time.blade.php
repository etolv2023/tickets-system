<x-card :title="__('تقرير الوقت')" flush>
    <x-slot:actions>
        <span class="u-subtle">{{ __('الوقت لا علاقة له بالنقاط — ولا يخضع لعوامل تصفية الشركة/النوع أعلاه') }}</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('الموظف') }}</th>
                    <th class="table__cell--num">{{ __('ساعات مسجّلة') }}</th>
                    <th class="table__cell--num">{{ __('تذاكر') }}</th>
                    <th class="table__cell--num">{{ __('دقة التقدير') }}</th>
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
                    <tr class="table__empty"><td colspan="4">{{ __('لا يوجد وقت مسجّل في هذه الفترة.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
