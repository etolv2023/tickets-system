/**
 * A select whose options come from the server as you type.
 *
 * The plain <select> it replaces rendered every row in the table. That is fine
 * at twenty companies and a liability at two thousand — page weight grows with
 * the data and the list stops being usable long before it stops loading.
 *
 * The real value posts through a hidden input, so the surrounding form, its
 * validation and its old() repopulation all work exactly as they did.
 */
export default function combobox({ resource, value = null, label = null, scope = null, placeholder = '' }) {
    return {
        resource,
        scope,
        placeholder,
        // Selected value, mirrored into the hidden input.
        value,
        // What the user sees. Coerced rather than defaulted: Blade sends an
        // explicit null for "nothing selected", and a default only applies to
        // undefined — so the box searched for the literal string "null".
        query: label ?? '',
        // The label of the current selection, to restore on a cancelled edit.
        selectedLabel: label ?? '',
        results: [],
        open: false,
        loading: false,
        highlighted: -1,
        timer: null,

        init() {
            // A dependent combobox (contacts under a company) clears itself
            // when its parent changes, otherwise it keeps a stale selection
            // that belongs to a different company.
            if (this.scope) {
                this.$watch('scopeValue', () => this.clear());
            }

            // The list is position:fixed and placed by measurement, so it can
            // escape a clipping ancestor. Cards carry overflow:hidden to keep
            // their rounded corners, and that was slicing the dropdown off.
            this.reposition = () => this.place();

            window.addEventListener('scroll', this.reposition, true);
            window.addEventListener('resize', this.reposition);

            this.$watch('open', (open) => open && this.$nextTick(() => this.place()));
        },

        destroy() {
            window.removeEventListener('scroll', this.reposition, true);
            window.removeEventListener('resize', this.reposition);
        },

        /**
         * Anchors the fixed list to the input, and flips it above when there
         * is more room up there than down.
         */
        place() {
            const list = this.$refs.list;

            if (!list || !this.open) {
                return;
            }

            const box = this.$refs.anchor.getBoundingClientRect();
            const below = window.innerHeight - box.bottom;
            const flip = below < 240 && box.top > below;

            // Physical left, not inset-inline-start: the measurement is a
            // physical rect, and in RTL the logical property would anchor the
            // list to the opposite edge from the one that was measured.
            // Match the input, but never go under the CSS min-width: a list
            // anchored to a narrow filter still has to fit a person's name.
            list.style.width = `${box.width}px`;
            list.style.left = `${box.left}px`;
            list.style.maxHeight = `${Math.max(140, (flip ? box.top : below) - 16)}px`;

            if (flip) {
                list.style.top = 'auto';
                list.style.bottom = `${window.innerHeight - box.top + 4}px`;
            } else {
                list.style.bottom = 'auto';
                list.style.top = `${box.bottom + 4}px`;
            }
        },

        get scopeValue() {
            return this.scope ? document.getElementById(this.scope)?.value ?? '' : '';
        },

        get showEmpty() {
            return this.open && !this.loading && this.results.length === 0;
        },

        onInput() {
            this.open = true;
            clearTimeout(this.timer);
            // A request per keystroke would be one per character typed.
            this.timer = setTimeout(() => this.search(), 220);
        },

        async search() {
            this.loading = true;

            try {
                const url = new URL(`/lookup/${this.resource}`, window.location.origin);
                url.searchParams.set('q', this.query ?? '');

                if (this.scope) {
                    url.searchParams.set('company', this.scopeValue);
                }

                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                this.results = response.ok ? (await response.json()).results : [];
            } catch (e) {
                this.results = [];
            } finally {
                this.loading = false;
                this.highlighted = this.results.length ? 0 : -1;
                // The list just changed height, so where it should sit may
                // have changed with it.
                this.$nextTick(() => this.place());
            }
        },

        select(item) {
            this.value = item.id;
            this.query = item.label;
            this.selectedLabel = item.label;
            this.open = false;

            // On the next tick, not now: the hidden input's value is an Alpine
            // binding, so at this instant it still holds the previous value and
            // a listener reading $event.target.value would get the old one.
            this.$nextTick(() => {
                this.$refs.field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        },

        clear() {
            this.value = null;
            this.query = '';
            this.selectedLabel = '';
            this.results = [];

            // Clearing IS a change. Without this a dependent field stays in
            // the state the last selection put it in.
            this.$nextTick(() => {
                this.$refs.field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        },

        close() {
            this.open = false;
            // Typing without picking anything is not a selection. Put the last
            // real choice back so the box never shows text that isn't the value.
            this.query = this.selectedLabel;
        },

        move(step) {
            if (!this.open) {
                this.open = true;

                return;
            }

            const count = this.results.length;

            if (count) {
                this.highlighted = (this.highlighted + step + count) % count;
            }
        },

        choose() {
            if (this.open && this.results[this.highlighted]) {
                this.select(this.results[this.highlighted]);
            }
        },
    };
}
