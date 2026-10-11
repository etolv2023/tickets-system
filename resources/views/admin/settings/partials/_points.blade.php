{{-- ★ (2026-08-05) كارت لوحده مش جوه «الإشعارات» ولا «السعة»: الإعداد ده
     بيغيّر فلوس فعلية في المكافآت، مش شكل ولا تنبيه. --}}
<x-card :title="__('النقاط والتأخير')" flush>
    <div class="settings__group">
        <div class="settings__row">
            <div>
                <div class="settings__row-label">{{ __('تراكم التأخير على المهام') }}</div>
                <p class="settings__row-hint">
                    {{ __('المهمة الفرعية التي تتجاوز تاريخ استحقاقها تُخصم نقاطها مرة واحدة. إذا فعّلت التراكم فسيخصمها الفحص الذي يعمل يوميًا الساعة 6 صباحًا') }}
                    <strong>{{ __('كل يوم') }}</strong> {{ __('طالما ما زالت متأخرة ولم تكتمل — ويُذكر في كل سطر أنه خصم متراكم.') }}
                </p>
            </div>
            <div class="settings__row-control">
                <label class="checkbox-row">
                    <input
                        type="checkbox"
                        id="late_penalty_accumulates"
                        name="late_penalty_accumulates"
                        value="1"
                        class="checkbox"
                        @checked(old('late_penalty_accumulates', $values['late_penalty_accumulates'] ?? false))
                    >
                    <span class="checkbox-row__label">
                        {{ __('فعّل التراكم') }}
                        <small>{{ __('عند اكتمال المهمة الفرعية يتوقف التراكم عليها فورًا.') }}</small>
                    </span>
                </label>
            </div>
        </div>
    </div>
</x-card>
