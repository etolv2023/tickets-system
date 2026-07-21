{{-- ★★★★★ (2026-07-21): the browser-notification prompt, on every page of the
     app shell — not just /notifications. The permission can only be asked for
     from a real user gesture (button click), so this can never auto-open the
     browser's own dialog; it can only make the ASK impossible to miss until
     the person answers it once, granted or denied. --}}
@auth
    <div x-data="notifyPermission(@js(config('webpush.public_key')))" x-cloak
         x-show="state === 'default' || state === 'denied'"
         class="notify-banner">
        <div class="notify-banner__text">
            <span class="notify-banner__title" x-text="label">فعّل إشعارات المتصفح</span>
            <span class="notify-banner__hint" x-text="hint"></span>
        </div>

        <x-button variant="primary" size="sm" type="button"
                  x-show="state === 'default'" @click="request()">فعّل دلوقتي</x-button>
    </div>
@endauth
