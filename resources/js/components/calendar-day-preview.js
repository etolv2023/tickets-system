/**
 * The modal a month/week cell opens. Its rows are fetched for the day that is
 * opened (CalendarController::day) and kept, so reopening a day is instant —
 * the page used to carry every day's rows as JSON, megabytes on a busy month.
 *
 * `url` is the day endpoint with the page's filters already on it.
 */
export default function calendarDayPreview(url) {
    return {
        open: false,
        date: null,
        label: '',
        selected: null,
        loading: false,
        failed: false,
        days: {},

        get items() {
            return this.date ? (this.days[this.date] ?? []) : [];
        },

        openDay(date, label) {
            this.date = date;
            this.label = label;
            this.selected = null;
            this.open = true;
            document.documentElement.style.overflow = 'hidden';

            // A day still loading for an earlier click must not leave this one
            // showing its spinner or its error.
            this.failed = false;

            if (date in this.days) {
                this.loading = false;
            } else {
                this.load(date);
            }
        },

        async load(date) {
            this.loading = true;
            this.failed = false;

            try {
                const target = new URL(url, window.location.origin);
                target.searchParams.set('date', date);

                const response = await fetch(target, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                this.days[date] = await response.json();
            } catch {
                // Not cached, so closing and reopening the day tries again.
                if (this.date === date) {
                    this.failed = true;
                }
            } finally {
                if (this.date === date) {
                    this.loading = false;
                }
            }
        },

        choose(item) {
            this.selected = item;
        },

        back() {
            this.selected = null;
        },

        close() {
            this.open = false;
            this.selected = null;
            this.failed = false;
            document.documentElement.style.overflow = '';
        },
    };
}
