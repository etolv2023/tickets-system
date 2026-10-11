<x-card :title="__('الإشعارات')" flush>
    <div class="settings__group">
        <div class="settings__row">
            <div>
                <div class="settings__row-label">{{ __('إشعارات البريد الإلكتروني') }}</div>
                <p class="settings__row-hint">{{ __('إشعارات النظام الداخلية مفعّلة دائمًا. هذا الخيار يضيف نسخة عبر البريد الإلكتروني.') }}</p>
            </div>
            <div class="settings__row-control">
                <label class="checkbox-row">
                    <input
                        type="checkbox"
                        id="email_notifications_enabled"
                        name="email_notifications_enabled"
                        value="1"
                        class="checkbox"
                        @checked(old('email_notifications_enabled', $values['email_notifications_enabled'] ?? false))
                    >
                    <span class="checkbox-row__label">
                        {{ __('فعّل إشعارات البريد الإلكتروني') }}
                        <small>{{ __('يتطلب إعدادات SMTP في ملف ‎.env.') }}</small>
                    </span>
                </label>
            </div>
        </div>
    </div>
</x-card>
