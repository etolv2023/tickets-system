<x-card title="حِمل التيم" flush>
    <x-slot:actions>
        <span class="u-subtle">التذاكر المفتوحة دلوقتي — مش محكومة بالشهر المختار</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th>المبرمج</th>
                    <th class="table__cell--num">فرونت</th>
                    <th class="table__cell--num">باك</th>
                    <th class="table__cell--num">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($load as $person)
                    <tr>
                        <td>
                            <div class="row">
                                <x-avatar :user="$person" size="sm" />
                                <a href="{{ route('reports.employee', $person) }}">{{ $person->name }}</a>
                            </div>
                        </td>
                        <td class="table__cell--num">{{ $person->frontend_open }}</td>
                        <td class="table__cell--num">{{ $person->backend_open }}</td>
                        <td class="table__cell--num">{{ $person->frontend_open + $person->backend_open }}</td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="4">مفيش شغل مفتوح على حد.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
