/**
 * F20.2 — the service worker.
 *
 * This is the only code in the system that runs when the tab is hidden, the
 * window is minimised, or the browser is closed. Everything the in-page bell
 * could not do is here, for exactly that reason.
 *
 * ★ The push arrives EMPTY. No ticket title, no customer name, no message body
 * ever passes through the push service — it only learns that a notification
 * exists. The content is fetched from our own server, with the user's own
 * session cookie, at the moment it is displayed.
 *
 * Not built by Vite: a service worker must be served from the path whose scope
 * it claims, and a hashed filename under /build would scope it to /build.
 */

self.addEventListener('install', () => {
    // No cache to warm — take over immediately instead of waiting for every
    // old tab to close.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
    event.waitUntil(show());
});

async function show() {
    let title = 'نظام التذاكر';
    let body = 'فيه تحديث على تذكرة.';
    let url = '/notifications';

    try {
        const response = await fetch('/notifications/count', {
            headers: { Accept: 'application/json' },
            // Same-origin: this is what carries the session, and it is why the
            // push itself never needs to contain anything.
            credentials: 'same-origin',
        });

        if (response.ok) {
            const payload = await response.json();

            if (payload.latest) {
                body = payload.latest.message;
                url = payload.latest.url;
            }

            if (payload.unread > 1) {
                title = `نظام التذاكر — ${payload.unread} إشعارات`;
            }
        }
    } catch (e) {
        // The session may have expired, or the machine may be offline. Showing
        // the generic notification is still better than showing nothing: the
        // browser will display its own "site updated in the background" notice
        // if we resolve without one.
    }

    // A service worker has no Audio API — it runs outside every page, so it
    // cannot play the app's own chime. What it CAN do is ask an open tab to
    // play it, which covers the common case of the browser being open with the
    // site in a background tab. With no tab open at all, the only sound
    // available is the operating system's own notification sound.
    await chimeInAnOpenTab();

    return self.registration.showNotification(title, {
        body,
        icon: '/favicon.svg',
        badge: '/favicon.svg',
        dir: 'rtl',
        lang: 'ar',
        // One tag: arriving to fifteen separate boxes after lunch is worse
        // than arriving to one that says fifteen.
        tag: 'tickets-notification',
        renotify: true,
        // Explicit rather than implied: some platforms have defaulted this the
        // other way, and a silent notification is the bug being reported.
        silent: false,
        data: { url },
    });
}

async function chimeInAnOpenTab() {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

    // One tab, not all of them — five open tabs should not chime five times.
    windows[0]?.postMessage({ type: 'notification-chime' });
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data?.url ?? '/notifications';

    event.waitUntil((async () => {
        const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        // Reuse a tab that is already open on this site rather than stacking a
        // new one every time a notification is clicked.
        for (const client of windows) {
            if (new URL(client.url).origin === self.location.origin) {
                await client.focus();

                return client.navigate(target);
            }
        }

        return self.clients.openWindow(target);
    })());
});
