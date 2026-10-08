export default function tableColumns({ storageKey, defaults }) {
    const allowed = ['priority', 'status', 'assignees', 'creator', 'date', 'deadline'];

    return {
        visibleColumns: [...defaults],

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey));

                if (Array.isArray(saved)) {
                    this.visibleColumns = saved.filter((column) => allowed.includes(column));
                }
            } catch {
                this.visibleColumns = [...defaults];
            }
        },

        isVisible(column) {
            return this.visibleColumns.includes(column);
        },

        toggle(column) {
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
