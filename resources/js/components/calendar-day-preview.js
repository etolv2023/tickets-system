export default function calendarDayPreview(days) {
    return {
        open: false,
        date: null,
        label: '',
        selected: null,

        get items() {
            return this.date ? (days[this.date] ?? []) : [];
        },

        openDay(date, label) {
            this.date = date;
            this.label = label;
            this.selected = null;
            this.open = true;
            document.documentElement.style.overflow = 'hidden';
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
            document.documentElement.style.overflow = '';
        },
    };
}
