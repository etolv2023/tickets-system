<x-card :title="__('توزيع التذاكر')" flush>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('النوع') }}</th>
                    <th class="table__cell--num">{{ __('الإجمالي') }}</th>
                    <th class="table__cell--num">{{ __('محلولة') }}</th>
                    <th class="table__cell--num">{{ __('مفتوحة') }}</th>
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
                    <tr class="table__empty"><td colspan="4">{{ __('مفيش تذاكر في الفترة دي.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
