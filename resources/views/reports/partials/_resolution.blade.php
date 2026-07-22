<div class="form-grid">
    <x-card title="زمن الحل بالأولوية" flush>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>الأولوية</th>
                        <th class="table__cell--num">متوسط الساعات</th>
                        <th class="table__cell--num">العدد</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resolution['byPriority'] as $row)
                        <tr>
                            @php($priority = \App\Casts\PriorityValue::for($row->k))
                            <td>
                                <x-badge :variant="$priority->variant()" :icon="$priority->icon()">{{ $priority->label() }}</x-badge>
                            </td>
                            <td class="table__cell--num">{{ round($row->avg_hours) }}</td>
                            <td class="table__cell--num">{{ $row->n }}</td>
                        </tr>
                    @empty
                        <tr class="table__empty"><td colspan="3">مفيش تذاكر اتحلت.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="زمن الحل بالنوع" flush>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>النوع</th>
                        <th class="table__cell--num">متوسط الساعات</th>
                        <th class="table__cell--num">العدد</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($resolution['byType'] as $row)
                        @php($enum = \App\Casts\TicketTypeValue::for($row->k))
                        <tr>
                            <td><x-badge :variant="$enum->variant()">{{ $enum->label() }}</x-badge></td>
                            <td class="table__cell--num">{{ round($row->avg_hours) }}</td>
                            <td class="table__cell--num">{{ $row->n }}</td>
                        </tr>
                    @empty
                        <tr class="table__empty"><td colspan="3">مفيش تذاكر اتحلت.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</div>
