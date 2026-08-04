{{-- ★ (2026-08-05) كارت لوحده مش جوه «الإشعارات» ولا «السعة»: الإعداد ده
     بيغيّر فلوس فعلية في المكافآت، مش شكل ولا تنبيه. --}}
<x-card title="النقاط والتأخير" flush>
    <div class="settings__group">
        <div class="settings__row">
            <div>
                <div class="settings__row-label">تراكم التأخير على التاسكات</div>
                <p class="settings__row-hint">
                    الصب تاسك اللي بيعدّي تاريخ استحقاقها بتتخصم نقطها بالسالب مرة واحدة.
                    لو فعّلت التراكم، الفحص اللي بيشتغل كل يوم الساعة ٦ الصبح هيخصمها
                    <strong>كل يوم</strong> طول ما هي لسه متأخرة ولسه مخلصتش — والسبب
                    بيتكتب في كل سطر إنه خصم متراكم.
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
                        فعّل التراكم
                        <small>لما تخلص الصب تاسك، التراكم بيقف عليها فوراً.</small>
                    </span>
                </label>
            </div>
        </div>
    </div>
</x-card>
