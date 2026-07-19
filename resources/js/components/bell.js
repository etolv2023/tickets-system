/**
 * Keeps the bell's unread count fresh, and announces new ones.
 *
 * Polling, not a websocket — and that is a deliberate placeholder, not an
 * oversight. Real-time needs a broadcast server and a decision about Redis
 * that has not been made yet. When it is, the only thing that changes is where
 * `apply()` gets called from; everything below stays.
 *
 * ★ It used to stop polling entirely on a hidden tab. That is exactly the case
 * this now has to serve: the point of a sound and a system notification is to
 * reach someone who is working in another window. So a hidden tab keeps
 * polling — at half the rate, which is the compromise with the original
 * concern about a whole team hitting this forever.
 */
const INTERVAL = 45_000;

/**
 * 60s, not 90s, because that is the floor the browser will actually honour.
 *
 * Chrome applies "intensive wake-up throttling" to a tab hidden for more than
 * five minutes: timers fire at most once a minute, and a minimised window can
 * be frozen outright. Asking for 90s under a 60s ceiling just means waiting
 * for the following tick. This is as close to timely as a page-level timer
 * gets — actual background delivery needs a service worker, which is a
 * separate decision (F20.2).
 */
const HIDDEN_INTERVAL = 60_000;

export default function bell(initial = 0) {
    return {
        count: initial,
        timer: null,
        // Seeded from the first render so a page load is never itself an
        // "arrival" — otherwise every navigation would chime.
        announced: null,
        sound: null,
        baseTitle: document.title,

        init() {
            this.schedule();

            // The service worker has no Audio API, so when a push arrives it
            // asks whichever tab is open to play the chime for it. This is the
            // only path that gives the app's own sound while the tab is in the
            // background — the poll below is timer-throttled there.
            navigator.serviceWorker?.addEventListener('message', (event) => {
                if (event.data?.type === 'notification-chime') {
                    this.chime();
                    this.refresh();
                }
            });

            // Coming back to the tab is exactly when the number is most stale.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.refresh();
                }

                this.schedule();
            });
        },

        schedule() {
            clearInterval(this.timer);

            this.timer = setInterval(
                () => this.refresh(),
                document.visibilityState === 'visible' ? INTERVAL : HIDDEN_INTERVAL
            );
        },

        async refresh() {
            try {
                const response = await fetch('/notifications/count', {
                    headers: { Accept: 'application/json' },
                });

                if (response.ok) {
                    const payload = await response.json();

                    this.apply(payload.unread, payload.latest);
                }
            } catch (e) {
                // A failed poll is not worth telling anyone about; the next
                // one is a minute away.
            }
        },

        apply(unread, latest = null) {
            const previous = this.count;

            this.count = Number(unread) || 0;
            this.retitle();

            // The id, not the count: marking one read and receiving another
            // leaves the count unchanged while something genuinely new arrived.
            if (latest === null) {
                this.announced = null;

                return;
            }

            const first = this.announced === null;

            if (latest.id === this.announced) {
                return;
            }

            this.announced = latest.id;

            // The first poll after a page load is establishing where we are,
            // not reporting an arrival.
            if (first || this.count <= previous) {
                return;
            }

            this.chime();
            this.notify(latest);
        },

        /** The tab title carries it too, for anyone who blocked notifications. */
        retitle() {
            document.title = this.count > 0
                ? `(${this.label}) ${this.baseTitle}`
                : this.baseTitle;
        },

        chime() {
            try {
                // Built lazily: constructing it before the first user gesture
                // gets it blocked by the autoplay policy on some browsers.
                this.sound ??= new Audio('/sounds/notify.wav');
                this.sound.currentTime = 0;
                this.sound.play().catch(() => {
                    // Autoplay refused — the title and the badge still carry it.
                });
            } catch (e) {
                // No audio support. Not a reason to lose the notification.
            }
        },

        notify(latest) {
            if (!('Notification' in window) || Notification.permission !== 'granted') {
                return;
            }

            const notification = new Notification('نظام التذاكر', {
                body: latest.message,
                icon: '/favicon.svg',
                // Same tag replaces rather than stacks: ten arrivals while you
                // were away should not be ten boxes to dismiss.
                tag: 'tickets-notification',
            });

            notification.addEventListener('click', () => {
                window.focus();
                window.location.href = latest.url;
            });
        },

        get label() {
            return this.count > 99 ? '99+' : String(this.count);
        },
    };
}
