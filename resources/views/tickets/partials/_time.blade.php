@php
    $estimate = (float) ($ticket->original_estimate_hours ?? 0);
    $spent = (float) $ticket->spent_hours;
    // Capped at 100% so the bar can't overflow; the colour carries the overrun.
    $percent = $estimate > 0 ? min(100, (int) round($spent / $estimate * 100)) : 0;
    $remaining = max(0, $estimate - $spent);
@endphp

<x-card :title="__('الوقت')">
    <div class="stack stack--tight">
        @if ($estimate > 0)
            {{-- F09: green inside the estimate, amber over it, red past double. --}}
            <div class="estimate estimate--{{ $ticket->estimateVariant() }}">
                <div class="estimate__row">
                    <span>{{ __('المقدَّر') }} <strong>{{ rtrim(rtrim(number_format($estimate, 2), '0'), '.') }}</strong> {{ __('س') }}</span>
                    <span class="estimate__spent">{{ __('الفعلي :hours س', ['hours' => rtrim(rtrim(number_format($spent, 2), '0'), '.')]) }}</span>
                </div>

                <div class="estimate__bar">
                    <div class="estimate__fill" style="--value: {{ $percent }}%"></div>
                </div>

                <div class="estimate__row">
                    <span class="u-subtle">{{ __('المتبقي :hours س', ['hours' => rtrim(rtrim(number_format($remaining, 2), '0'), '.')]) }}</span>
                    @if ($spent > $estimate)
                        <span class="u-subtle">{{ __('تجاوز التقدير بـ :hours س', ['hours' => rtrim(rtrim(number_format($spent - $estimate, 2), '0'), '.')]) }}</span>
                    @endif
                </div>
            </div>
        @else
            <p class="field__hint">
                {{ __('لا يوجد تقدير. أضف مهام فرعية بتقديرات وستُجمع هنا تلقائيًا.') }}
                @if ($spent > 0)
                    <br>{{ __('المسجّل حتى الآن:') }} <strong>{{ rtrim(rtrim(number_format($spent, 2), '0'), '.') }}</strong> {{ __('س.') }}
                @endif
            </p>
        @endif

        @can('time.log')
            <hr class="divider">

            <form method="POST" action="{{ route('tickets.time.store', $ticket) }}" class="stack stack--tight">
                @csrf

                <div class="form-grid">
                    <x-field name="hours" :label="__('ساعات')" type="number" step="0.25" min="0.25" max="24"
                             required placeholder="1.5" />
                    <x-field name="spent_on" :label="__('التاريخ')" type="date"
                             :value="now(config('app.display_timezone'))->toDateString()"
                             required max="{{ now(config('app.display_timezone'))->toDateString() }}" />
                </div>

                @if ($ticket->subtasks->isNotEmpty())
                    <x-field name="subtask_id" :label="__('على أي مهمة فرعية؟')">
                        <select id="subtask_id" name="subtask_id" class="select">
                            <option value="">{{ __('التذكرة نفسها') }}</option>
                            @foreach ($ticket->subtasks as $subtask)
                                <option value="{{ $subtask->id }}">{{ $subtask->title }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif

                <x-field name="note" :label="__('ملاحظة')" :placeholder="__('اختياري')" />

                <x-button variant="secondary" size="sm" block>{{ __('تسجيل الوقت') }}</x-button>
            </form>

            <div class="lazy-panel" x-data="lazyPanel(@js(route('tickets.panels.time', $ticket)))"
                 x-effect="tab === 'time' && load()" x-html="html"></div>
        @endcan
    </div>
</x-card>
