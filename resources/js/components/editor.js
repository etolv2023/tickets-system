import Quill from 'quill';

/**
 * Rich text editor (F04.1). RTL by default, code blocks LTR.
 *
 * Whatever this produces is untrusted: the server runs Purifier over it before
 * storing. Nothing here is a security control — the toolbar is only what the
 * user is *offered*, not what they're limited to.
 */
const FULL = [
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    [{ header: [3, 4, false] }],
    ['link', 'blockquote', 'code-block'],
    ['clean'],
];

// The portal set. Neither list offers an image button, so nothing here can
// inline a base64 payload — worth keeping true as the toolbars diverge.
const SIMPLE = [['bold', 'italic'], [{ list: 'ordered' }, { list: 'bullet' }], ['link'], ['clean']];

/**
 * ★ (2026-08-02) An image dropped or pasted into the editor becomes an
 * ATTACHMENT, not part of the text.
 *
 * Quill's own handling would inline it as a base64 data URI, which means a 2MB
 * screenshot lands inside tickets.description — a LONGTEXT column read on every
 * list query — and skips AttachmentService entirely: no finfo check, no
 * re-encode, no size limit, no thumbnail (§ 4.3, § 5). That is why there has
 * never been an image button on the toolbar.
 *
 * So the file is handed to the uploader instead. The two components don't know
 * about each other: this dispatches a cancelable event, and whoever is holding
 * the attachment list calls preventDefault() to claim it. If nothing does — the
 * edit form has no uploader — the user is told, rather than the paste silently
 * doing nothing.
 */
export const FILES_EVENT = 'editor:files';

function imagesFrom(dataTransfer) {
    return [...(dataTransfer?.items ?? [])]
        .filter((item) => item.kind === 'file')
        .map((item) => item.getAsFile())
        .filter((file) => file && file.type.startsWith('image/'));
}

/**
 * The placeholder src the editor writes for a pasted image, swapped for the real
 * attachment URL server-side once the row exists (AttachmentService::
 * resolveInlineImages). Absolute because the purifier only passes http(s).
 */
function pendingUrl(token) {
    return `${window.location.origin}/attachments/pending/${token}`;
}

function newToken() {
    return (crypto.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`)
        .replace(/[^A-Za-z0-9-]/g, '');
}

export default function editor({ name, value = '', placeholder = '', simple = false }) {
    return {
        quill: null,
        notice: '',
        noticeTimer: null,
        /** blob URL -> token, for the swap in sync(). */
        pending: new Map(),

        /**
         * Hands the files to the attachment list and draws each accepted one
         * where the cursor is. The picture stays in the sentence it belongs to;
         * the bytes go through the attachment pipeline. @returns {boolean}
         */
        /**
         * Where to put the picture, without ever asking Quill to re-read the
         * live DOM selection.
         *
         * ★ (2026-08-03) handOff used to end its fallback with
         * `this.quill.getSelection(true)`. The `true` makes Quill focus the
         * editor and rebuild its range from the native selection —
         * getSelection → update → getRange → normalizedToRange — and that last
         * step maps each selection node back to a blot. During a paste the node
         * it lands on is not always one Quill owns, so the map yields null and
         * it dies on `null.offset`.
         *
         * It THROWS, it does not return null, so the `?.` that used to guard it
         * never ran: handOff blew up before insertEmbed, before setSelection,
         * and before the notice was set. The file was already in the attachment
         * list by then — which is exactly the reported symptom, a picture that
         * lands in المرفقات and never appears in the text. Confirmed from a real
         * browser's stack trace (Proxy.getSelection ← Proxy.handOff), not from
         * reading the code.
         *
         * The bare getSelection() returns Quill's own last-known range without
         * touching focus or the DOM, and the catch covers whatever is left.
         */
        caret() {
            try {
                return this.quill.getSelection()?.index ?? this.quill.getLength();
            } catch {
                return this.quill.getLength();
            }
        },

        handOff(files, range = null) {
            const tokens = files.map(() => newToken());

            const event = new CustomEvent(FILES_EVENT, {
                detail: { files, tokens, accepted: [] },
                bubbles: true,
                cancelable: true,
            });

            const taken = ! this.$root.dispatchEvent(event);
            const accepted = event.detail.accepted ?? [];

            if (taken && accepted.length) {
                // Local blob for the preview, token remembered against it: the
                // blob is what the user sees while writing, and sync() swaps it
                // for the placeholder in what actually gets submitted.
                let at = range?.index ?? this.caret();

                accepted.forEach((token, i) => {
                    const blob = URL.createObjectURL(files[tokens.indexOf(token)]);
                    this.pending.set(blob, token);
                    this.quill.insertEmbed(at, 'image', blob, 'user');
                    at += 1;
                    this.quill.setSelection(at + (i === accepted.length - 1 ? 1 : 0), 0, 'silent');
                });
            }

            this.notice = ! taken
                ? 'الصور بتتضاف من المرفقات — مفيش مرفقات في الصفحة دي.'
                : accepted.length
                    ? `${accepted.length > 1 ? `${accepted.length} صور اتحطوا` : 'الصورة اتحطت'} هنا وكمان في المرفقات.`
                    : 'الصورة مادخلتش — شوف رسالة المرفقات تحت.';

            clearTimeout(this.noticeTimer);
            this.noticeTimer = setTimeout(() => { this.notice = ''; }, 5000);

            return taken;
        },

        init() {
            const holder = this.$refs.editor;
            const input = this.$refs.input;

            this.quill = new Quill(holder, {
                theme: 'snow',
                placeholder,
                modules: {
                    toolbar: simple ? SIMPLE : FULL,
                    // Pasting from Word drags in a wall of inline styles. Taking
                    // only the text and the tags we allow keeps that out. F04.1
                    clipboard: { matchVisual: false },
                },
            });

            this.quill.root.setAttribute('dir', 'rtl');

            // Capture phase: Quill binds its own paste/drop handlers on the same
            // element, and the base64 insert has to be stopped before they run.
            this.quill.root.addEventListener('paste', (event) => {
                const images = imagesFrom(event.clipboardData);

                if (images.length) {
                    // Read the caret BEFORE preventDefault, and pass it on — the
                    // drop handler below always did, the paste handler never did,
                    // and that asymmetry is what pushed handOff onto the
                    // getSelection(true) path that throws. Symmetric now.
                    const at = this.quill.getSelection();

                    event.preventDefault();
                    event.stopPropagation();
                    this.handOff(images, at);
                }
            }, true);

            this.quill.root.addEventListener('drop', (event) => {
                const images = imagesFrom(event.dataTransfer);

                if (images.length) {
                    event.preventDefault();
                    event.stopPropagation();
                    // A drop lands where the pointer is, not where the caret was.
                    const at = this.quill.getSelection();
                    this.handOff(images, at);
                }
            }, true);

            // An <img> can also arrive inside pasted HTML (from another page, or
            // Word), which is not a file and never reaches the handlers above.
            // Only a data: URI is dropped — that is the base64 payload this whole
            // design exists to keep out of the description column.
            //
            // NOT a blanket drop: dangerouslyPasteHTML below runs through these
            // same matchers, so stripping every img would silently delete a
            // ticket's existing screenshots the moment someone opened it to edit
            // a typo.
            this.quill.clipboard.addMatcher('IMG', (node, delta) => (
                (node.getAttribute?.('src') ?? '').startsWith('data:') ? { ops: [] } : delta
            ));

            if (value) {
                this.quill.clipboard.dangerouslyPasteHTML(value, 'silent');
            }

            const sync = () => {
                let html = this.quill.getSemanticHTML();

                // What's on screen is a blob: URL — meaningless to anyone else
                // and dead the moment this tab closes. What gets submitted is the
                // placeholder, which the server turns into the real attachment
                // URL. Any blob with no token left (image dragged in from another
                // tab, say) is dropped rather than saved as a broken link.
                this.pending.forEach((token, blob) => {
                    html = html.split(blob).join(pendingUrl(token));
                });

                html = html.replace(/<img[^>]*src="blob:[^"]*"[^>]*>/g, '');

                input.value = this.quill.getText().trim() === '' && ! html.includes('<img') ? '' : html;
            };

            this.quill.on('text-change', sync);
            sync();
        },
    };
}
