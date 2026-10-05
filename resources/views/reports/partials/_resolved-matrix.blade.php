{{-- The matrix. A ticket two people hold counts once for each of them, so a
     column's total is "how many of this type got resolved", not the sum of
     the column. The headline tile already says the distinct figure. --}}
<x-card title="مين حل إيه" flush>
    <x-slot:actions>
        <span class="u-subtle">{{ \App\Models\Ticket::RELATIONS[$data['relation']] }} · من <span class="u-nums">{{ $filters['from'] }}</span> إلى <span class="u-nums">{{ $filters['to'] }}</span> على تاريخ الحل</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover matrix">
            <thead>
                <tr>
                    <th>الموظف</th>
                    @foreach ($data['types'] as $key => $label)
                        @php($type = \App\Casts\TicketTypeValue::for($key))
                        <th class="table__cell--num">
                            <x-badge :variant="$type->variant()" :icon="$type->icon()">{{ $label }}</x-badge>
                        </th>
                    @endforeach
                    <th class="table__cell--num">الإجمالي</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($data['rows'] as $row)
                    <tr>
                        <td>
                            <div class="row">
                                <x-avatar :user="$row->user" size="sm" />
                                <a href="{{ route('reports.resolved-by-type', array_filter($filters) + ['person' => $row->user->id]) }}">
                                    {{ $row->user->name }}
                                </a>
                                @if ($row->user->role)
                                    <span class="u-subtle">{{ $row->user->role->name_ar }}</span>
                                @endif
                            </div>
                        </td>
                        @foreach ($data['types'] as $key => $label)
                            <td @class(['table__cell--num', 'matrix__zero' => ($row->counts[$key] ?? 0) === 0])>
                                {{ $row->counts[$key] ?? 0 }}
                            </td>
                        @endforeach
                        <td class="table__cell--num matrix__total">{{ $row->total }}</td>
                    </tr>
                @empty
                    <tr class="table__empty">
                        <td colspan="{{ count($data['types']) + 2 }}">محدش حل حاجة في الفترة دي بالفلاتر دي.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($data['rows']->isNotEmpty())
                <tfoot>
                    <tr class="matrix__foot">
                        <td>إجمالي النوع</td>
                        @foreach ($data['types'] as $key => $label)
                            <td class="table__cell--num">{{ $data['totals'][$key] ?? 0 }}</td>
                        @endforeach
                        <td class="table__cell--num">{{ array_sum($data['totals']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</x-card>
