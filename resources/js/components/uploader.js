/**
 * Multi-file picker with drag-drop and clipboard paste (F04.2).
 *
 * Client-side checks here are courtesy, not security: AttachmentService re-reads
 * every byte with finfo and re-encodes every image on the server.
 */
export default function uploader({ maxBytes = 5 * 1024 * 1024, maxVideoBytes = 200 * 1024 * 1024 } = {}) {
    return {
        files: [],
        dragging: false,
        error: '',

        init() {
            // Paste a screenshot straight into the page — the common case for a
            // support agent who just pressed PrintScreen.
            this.$root.addEventListener('paste', (event) => {
                const items = [...(event.clipboardData?.items ?? [])]
                    .filter((i) => i.kind === 'file')
                    .map((i) => i.getAsFile())
                    .filter(Boolean);

                if (items.length) {
                    event.preventDefault();
                    this.add(items);
                }
            });
        },

        pick(event) {
            this.add([...event.target.files]);
        },

        drop(event) {
            this.dragging = false;
            this.add([...event.dataTransfer.files]);
        },

        add(incoming) {
            this.error = '';

            for (const file of incoming) {
                const isVideo = file.type.startsWith('video/');
                const limit = isVideo ? maxVideoBytes : maxBytes;

                if (file.size > limit) {
                    this.error = `«${file.name}» أكبر من ${isVideo ? 200 : 5} ميجا.`;
                    continue;
                }

                this.files.push({
                    file,
                    name: file.name,
                    size: this.human(file.size),
                    preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                });
            }

            this.sync();
        },

        remove(index) {
            const [gone] = this.files.splice(index, 1);

            if (gone?.preview) {
                URL.revokeObjectURL(gone.preview);
            }

            this.error = '';
            this.sync();
        },

        /* The file input is the source of truth at submit time, so rebuild it. */
        sync() {
            const data = new DataTransfer();
            this.files.forEach(({ file }) => data.items.add(file));
            this.$refs.input.files = data.files;
        },

        human(bytes) {
            const units = ['بايت', 'كيلوبايت', 'ميجا'];
            let n = bytes;
            let u = 0;

            while (n >= 1024 && u < units.length - 1) {
                n /= 1024;
                u++;
            }

            return `${n.toFixed(u === 0 ? 0 : 1)} ${units[u]}`;
        },
    };
}
