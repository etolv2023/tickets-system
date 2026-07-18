<x-card title="كل تسجيل على حدة" flush>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>التاريخ</th>
                    <th>التذكرة</th>
                    <th>الصب تاسك</th>
                    <th>ملاحظة</th>
                    <th class="table__cell--num">ساعات</th>
                    <th class="table__cell--actions"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td class="u-nums">{{ $entry->spent_on->translatedFormat('D j M') }}</td>
                        <td>
                            <a href="{{ route('tickets.show', $entry->ticket_id) }}">
                                <span class="u-mono u-ltr">{{ $entry->ticket->ticket_number }}</span>
                                {{ Str::limit($entry->ticket->title, 40) }}
                            </a>
                        </td>
                        <td>{{ $entry->subtask?->title ?? '—' }}</td>
                        <td class="u-subtle">{{ $entry->note ?? '—' }}</td>
                        <td class="table__cell--num">{{ rtrim(rtrim($entry->hours, '0'), '.') }}</td>
                        <td class="table__cell--actions">
                            <form method="POST" action="{{ route('time.destroy', $entry) }}">
                                @csrf
                                @method('DELETE')
                                <x-button variant="ghost" size="sm">حذف</x-button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>
