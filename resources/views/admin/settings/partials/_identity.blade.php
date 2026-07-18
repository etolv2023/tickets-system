<x-card title="هوية النظام" flush>
    <div class="settings__group">
        <div class="settings__row">
            <div>
                <div class="settings__row-label">اسم النظام</div>
                <p class="settings__row-hint">بيظهر في السايدبار وعنوان الصفحة.</p>
            </div>
            <div class="settings__row-control">
                <input
                    type="text"
                    id="app_name"
                    name="app_name"
                    value="{{ old('app_name', $values['app_name'] ?? '') }}"
                    required
                    @class(['input', 'input--invalid' => $errors->has('app_name')])
                >
            </div>
        </div>

        <div class="settings__row">
            <div>
                <div class="settings__row-label">اللوجو</div>
                <p class="settings__row-hint">PNG أو JPG أو WEBP، حد أقصى 2 ميجا. بيتعاد معالجته ويتصغّر لـ 400×120.</p>
            </div>
            <div class="settings__row-control">
                <div class="stack stack--tight">
                    @if (! empty($values['app_logo']))
                        <img src="{{ asset('storage/' . $values['app_logo']) }}" alt="اللوجو الحالي" height="32">

                        <label class="checkbox-row">
                            <input type="checkbox" name="remove_logo" value="1" class="checkbox">
                            <span class="checkbox-row__label">شيل اللوجو الحالي</span>
                        </label>
                    @endif

                    <input
                        type="file"
                        id="app_logo"
                        name="app_logo"
                        accept="image/png,image/jpeg,image/webp"
                        @class(['input', 'input--invalid' => $errors->has('app_logo')])
                    >
                </div>
            </div>
        </div>
    </div>
</x-card>
