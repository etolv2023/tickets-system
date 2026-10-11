import { t } from '../i18n';

/**
 * The "turn on browser notifications" control.
 *
 * Permission can only be asked for from a real user gesture — asking on page
 * load is refused by every current browser and, on repeat, poisons the origin
 * so the prompt never appears again. So it lives behind a button the user
 * presses, and the button says which of the three states it is in.
 *
 * Granting also registers the service worker and subscribes to push, which is
 * what makes notifications arrive while the tab is closed. Without it the tab
 * has to be open, and a hidden tab's timers are throttled or frozen by the
 * browser — which is the whole reason this exists.
 */
export default function notifyPermission(vapidKey = '') {
    return {
        state: 'unsupported',
        busy: false,

        init() {
            if (!('Notification' in window)) {
                return;
            }

            this.state = Notification.permission;

            // Already allowed on a previous visit: make sure this browser is
            // still subscribed. Service workers get unregistered by profile
            // cleaners and the subscription expires on its own.
            if (this.state === 'granted') {
                this.subscribe();
            }
        },

        async request() {
            if (this.state !== 'default' || this.busy) {
                return;
            }

            this.busy = true;
            this.state = await Notification.requestPermission();

            if (this.state === 'granted') {
                await this.subscribe();
            }

            this.busy = false;
        },

        /**
         * Silent by design: push is an enhancement over the in-page bell, and
         * a browser that refuses it (no service worker support, keys not
         * configured, offline) should degrade rather than complain.
         */
        async subscribe() {
            if (!vapidKey || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                return;
            }

            try {
                const registration = await navigator.serviceWorker.register('/sw.js');
                await navigator.serviceWorker.ready;

                const subscription = await registration.pushManager.subscribe({
                    // Required by every browser: a push that can't show a
                    // notification is not allowed to arrive at all.
                    userVisibleOnly: true,
                    applicationServerKey: base64UrlToUint8Array(vapidKey),
                });

                await fetch('/push/subscribe', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                        Accept: 'application/json',
                    },
                    body: JSON.stringify(subscription.toJSON()),
                });
            } catch (e) {
                // Left to the bell.
            }
        },

        get label() {
            return {
                granted: t('الإشعارات مفعّلة'),
                denied: t('الإشعارات محظورة في المتصفح'),
                unsupported: t('متصفحك لا يدعم الإشعارات'),
                default: t('فعّل إشعارات المتصفح'),
            }[this.state];
        },

        get hint() {
            return {
                granted: t('ستصلك حتى لو كانت علامة التبويب مغلقة والمتصفح يعمل في الخلفية.'),
                denied: t('اسمح بالإشعارات لموقع «نظام التذاكر» من إعدادات الموقع في متصفحك.'),
                unsupported: '',
                default: t('لتصلك الإشعارات أثناء عملك على شيء آخر.'),
            }[this.state];
        },
    };
}

/** The subscribe() call wants raw bytes, not the base64url the server stores. */
function base64UrlToUint8Array(value) {
    const padded = (value + '='.repeat((4 - (value.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const binary = atob(padded);

    return Uint8Array.from([...binary].map((char) => char.charCodeAt(0)));
}
