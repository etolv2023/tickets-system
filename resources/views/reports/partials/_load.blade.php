<x-card :title="__('عبء عمل الفريق')" flush>
    <x-slot:actions>
        <span class="u-subtle">{{ __('التذاكر المفتوحة حاليًا — لا تخضع للشهر ولا لعوامل التصفية أعلاه') }}</span>
    </x-slot:actions>

    <div class="table-wrap">
        <table class="table table--hover">
            <thead>
                <tr>
                    <th>{{ __('الموظف') }}</th>
                    <th class="table__cell--num">{{ __('التذاكر المفتوحة') }}</th>
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
                        <td class="table__cell--num">{{ $person->open_load }}</td>
                    </tr>
                @empty
                    <tr class="table__empty"><td colspan="2">{{ __('لا يوجد عمل مفتوح لأي شخص.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-card>
