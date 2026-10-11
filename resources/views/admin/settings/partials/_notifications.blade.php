<x-card :title="__('الإشعارات')" flush>
    <div class="settings__group">
        <div class="settings__row">
            <div>
                <div class="settings__row-label">{{ __('إشعارات الإيميل') }}</div>
                <p class="settings__row-hint">{{ __('إشعارات جوه النظام شغالة دايماً. ده بيضيف نسخة على الإيميل كمان.') }}</p>
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
                        {{ __('فعّل إشعارات الإيميل') }}
                        <small>{{ __('محتاج إعدادات SMTP في ملف .env.') }}</small>
                    </span>
                </label>
            </div>
        </div>
    </div>
</x-card>
