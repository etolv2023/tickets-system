<x-card title="مهل الـ SLA" flush>
    <x-slot:actions>
        <span class="u-subtle">بالساعات من وقت فتح التذكرة</span>
    </x-slot:actions>

    <div class="settings__group">
        @foreach ($priorities as $priority)
            @php $key = $priority->slaSettingKey(); @endphp

            <div class="sla-row">
                <x-priority-stripe :priority="$priority" />

                <span class="sla-row__name">{{ $priority->label() }}</span>

                <input
                    type="number"
                    id="{{ $key }}"
                    name="{{ $key }}"
                    value="{{ old($key, $values[$key] ?? $priority->defaultSlaHours()) }}"
                    min="1"
                    max="8760"
                    required
                    @class(['input', 'sla-row__input', 'input--invalid' => $errors->has($key)])
                >

                <span class="settings__unit">ساعة</span>
            </div>
        @endforeach
    </div>
</x-card>
