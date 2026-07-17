/** Image gallery lightbox for ticket attachments (F04.2). */
export default function lightbox() {
    return {
        open: false,
        index: 0,
        items: [],

        show(items, index) {
            this.items = items;
            this.index = index;
            this.open = true;
        },

        close() {
            this.open = false;
        },

        next() {
            this.index = (this.index + 1) % this.items.length;
        },

        prev() {
            this.index = (this.index - 1 + this.items.length) % this.items.length;
        },

        get current() {
            return this.items[this.index] ?? null;
        },
    };
}
