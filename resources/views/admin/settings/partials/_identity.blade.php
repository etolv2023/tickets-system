<x-card :title="__('هوية النظام')" flush>
    <div class="settings__group">
        <div class="settings__row">
            <div>
                <div class="settings__row-label">{{ __('اسم النظام') }}</div>
                <p class="settings__row-hint">{{ __('يظهر في الشريط الجانبي وعنوان الصفحة.') }}</p>
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
                <div class="settings__row-label">{{ __('اسم النظام بالإنجليزية') }}</div>
                <p class="settings__row-hint">{{ __('يظهر في الواجهة الإنجليزية. إذا تُرك فارغًا يظهر الاسم العربي.') }}</p>
            </div>
            <div class="settings__row-control">
                <input
                    type="text"
                    id="app_name_en"
                    name="app_name_en"
                    value="{{ old('app_name_en', $values['app_name_en'] ?? '') }}"
                    dir="ltr"
                    @class(['input', 'input--invalid' => $errors->has('app_name_en')])
                >
            </div>
        </div>

        <div class="settings__row">
            <div>
                <div class="settings__row-label">{{ __('الشعار') }}</div>
                <p class="settings__row-hint">{{ __('PNG أو JPG أو WEBP، بحد أقصى 2 ميغابايت. تُعاد معالجته ويُصغَّر إلى 400×120.') }}</p>
            </div>
            <div class="settings__row-control">
                <div class="stack stack--tight">
                    @if (! empty($values['app_logo']))
                        <img src="{{ asset('storage/' . $values['app_logo']) }}" alt="{{ __('الشعار الحالي') }}" height="32">

                        <label class="checkbox-row">
                            <input type="checkbox" name="remove_logo" value="1" class="checkbox">
                            <span class="checkbox-row__label">{{ __('إزالة الشعار الحالي') }}</span>
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
