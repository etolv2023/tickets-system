/**
 * Keeps the bell's unread count fresh.
 *
 * Polling, not a websocket — and that is a deliberate placeholder, not an
 * oversight. Real-time needs a broadcast server and a decision about Redis
 * that has not been made yet. When it is, the only thing that changes is where
 * `apply()` gets called from; everything below stays.
 *
 * It backs off when the tab is hidden, because a bell nobody is looking at is
 * not worth a request every half minute across the whole team.
 */
const INTERVAL = 45_000;

export default function bell(initial = 0) {
    return {
        count: initial,
        timer: null,

        init() {
            this.schedule();

            // Coming back to the tab is exactly when the number is most stale.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.refresh();
                    this.schedule();
                } else {
                    clearInterval(this.timer);
                }
            });
        },

        schedule() {
            clearInterval(this.timer);
            this.timer = setInterval(() => this.refresh(), INTERVAL);
        },

        async refresh() {
            if (document.visibilityState !== 'visible') {
                return;
            }

            try {
                const response = await fetch('/notifications/count', {
                    headers: { Accept: 'application/json' },
                });

                if (response.ok) {
                    this.apply((await response.json()).unread);
                }
            } catch (e) {
                // A failed poll is not worth telling anyone about; the next
                // one is 45 seconds away.
            }
        },

        apply(unread) {
            this.count = Number(unread) || 0;
        },

        get label() {
            return this.count > 99 ? '99+' : String(this.count);
        },
    };
}
