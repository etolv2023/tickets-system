@php
    /* Normalised because old() hands back strings while the DB hands back ints. */
    $selectedDays = array_map('intval', (array) old('work_days', $values['work_days'] ?? []));
@endphp

<x-card title="أيام العمل والسعة" flush>
    <div class="settings__group">
        <div class="settings__row">
            <div>
                <div class="settings__row-label">السعة اليومية</div>
                <p class="settings__row-hint">ساعات إنتاجية في اليوم — مش ساعات الحضور. مؤشر السعة في الكاليندر بيتحسب منها.</p>
            </div>
            <div class="settings__row-control">
                <input
                    type="number"
                    id="daily_capacity_hours"
                    name="daily_capacity_hours"
                    value="{{ old('daily_capacity_hours', $values['daily_capacity_hours'] ?? 6) }}"
                    step="0.5"
                    min="0.5"
                    max="24"
                    required
                    @class(['input', 'input--invalid' => $errors->has('daily_capacity_hours')])
                >
                <span class="settings__unit">ساعة</span>
            </div>
        </div>

        <div class="settings__row">
            <div>
                <div class="settings__row-label">ساعات الدوام</div>
                <p class="settings__row-hint">
                    دي ساعات شغل مهلة الـ SLA — مش السعة. تذكرة عاجلة اتفتحت 4 عصراً
                    باقي مهلتها بيترحّل لأول يوم عمل، مش بيعدّي بالليل.
                </p>
            </div>
            <div class="settings__row-control">
                <input
                    type="time"
                    id="work_day_start"
                    name="work_day_start"
                    value="{{ old('work_day_start', $values['work_day_start'] ?? '09:00') }}"
                    required
                    @class(['input', 'input--invalid' => $errors->has('work_day_start')])
                >
                <span class="settings__unit" aria-hidden="true">—</span>
                <input
                    type="time"
                    id="work_day_end"
                    name="work_day_end"
                    value="{{ old('work_day_end', $values['work_day_end'] ?? '17:00') }}"
                    required
                    @class(['input', 'input--invalid' => $errors->has('work_day_end')])
                >
            </div>
        </div>

        <div class="settings__row">
            <div>
                <div class="settings__row-label">بداية الأسبوع</div>
                <p class="settings__row-hint">أول عمود في الكاليندر.</p>
            </div>
            <div class="settings__row-control">
                <select
                    id="week_start_day"
                    name="week_start_day"
                    @class(['select', 'select--invalid' => $errors->has('week_start_day')])
                >
                    @foreach ($weekDays as $number => $label)
                        <option value="{{ $number }}" @selected((int) old('week_start_day', $values['week_start_day'] ?? 6) === $number)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="settings__row">
            <div>
                <div class="settings__row-label">أيام العمل</div>
                <p class="settings__row-hint">الأيام دي بس الي بتتحسب في المهل والسعة.</p>
            </div>
            <div class="settings__row-control">
                <div class="stack stack--tight">
                    @foreach ($weekDays as $number => $label)
                        <label class="checkbox-row">
                            <input
                                type="checkbox"
                                name="work_days[]"
                                value="{{ $number }}"
                                class="checkbox"
                                @checked(in_array($number, $selectedDays, true))
                            >
                            <span class="checkbox-row__label">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-card>
