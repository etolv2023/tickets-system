import { FILES_EVENT } from './editor.js';

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

            // ★ (2026-08-02) An image pasted or dropped into the description
            // editor belongs here, not inlined as base64 in the text — see
            // editor.js. preventDefault() is how this says "I took them"; the
            // editor reads that back to tell the user where the image went.
            window.addEventListener(FILES_EVENT, (event) => {
                const files = event.detail?.files ?? [];

                // Already claimed. The ticket page carries a comment form with
                // its own editor and uploader, so more than one of these is
                // listening and without this the file would be added twice.
                if (! files.length || event.defaultPrevented) {
                    return;
                }

                // Only take files from an editor inside the same form. Otherwise
                // a screenshot pasted into the comment box would land in the
                // attachment list of a different form on the same page.
                const from = event.target?.closest?.('form');
                const mine = this.$root.closest('form');

                if (from && mine && from !== mine) {
                    return;
                }

                event.preventDefault();
                // Hand back what was accepted: a file rejected for size must not
                // leave a placeholder in the text pointing at nothing.
                event.detail.accepted = this.add(files, event.detail.tokens ?? []);
            });
        },

        pick(event) {
            this.add([...event.target.files]);
        },

        drop(event) {
            this.dragging = false;
            this.add([...event.dataTransfer.files]);
        },

        /**
         * @param {File[]} incoming
         * @param {string[]} tokens  editor tokens, positionally matched to
         *   `incoming`. Only a paste into the description supplies these; a file
         *   picked from disk has none and posts an empty token.
         * @returns {string[]} the tokens that were actually accepted, so the
         *   editor only draws a placeholder for a file that got through.
         */
        add(incoming, tokens = []) {
            this.error = '';

            const accepted = [];

            incoming.forEach((file, index) => {
                const isVideo = file.type.startsWith('video/');
                const limit = isVideo ? maxVideoBytes : maxBytes;

                if (file.size > limit) {
                    this.error = `«${file.name}» أكبر من ${isVideo ? 200 : 5} ميجا.`;
                    return;
                }

                const token = tokens[index] ?? null;

                this.files.push({
                    file,
                    token,
                    name: file.name,
                    size: this.human(file.size),
                    preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                });

                if (token) {
                    accepted.push(token);
                }
            });

            this.sync();

            return accepted;
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
