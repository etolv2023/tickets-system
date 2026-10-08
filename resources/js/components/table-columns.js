export default function tableColumns({ storageKey, defaults }) {
    const allowed = ['priority', 'status', 'assignees', 'creator', 'date', 'deadline'];

    return {
        visibleColumns: [...defaults],

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey));

                if (Array.isArray(saved)) {
                    const valid = saved.filter((column) => allowed.includes(column));

                    // A table with only its identity column is technically
                    // valid but operationally broken: long mixed-direction
                    // titles consume the whole row and none of the workflow
                    // state remains visible. Treat an empty saved set as a
                    // corrupt preference and recover the useful defaults.
                    this.visibleColumns = valid.length ? valid : [...defaults];
                }
            } catch {
                this.visibleColumns = [...defaults];
            }
        },

        isVisible(column) {
            return this.visibleColumns.includes(column);
        },

        toggle(column) {
            if (this.visibleColumns.length === 1 && this.isVisible(column)) {
                return;
            }

            this.visibleColumns = this.isVisible(column)
                ? this.visibleColumns.filter((item) => item !== column)
                : [...this.visibleColumns, column];
            this.persist();
        },

        reset() {
            this.visibleColumns = [...defaults];
            this.persist();
        },

        persist() {
            try {
                localStorage.setItem(storageKey, JSON.stringify(this.visibleColumns));
            } catch {
                // Storage can be unavailable in private browsing. The current
                // page still works; only persistence is skipped.
            }
        },
    };
}
