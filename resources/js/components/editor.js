import Quill from 'quill';

/**
 * ★ (2026-08-04) Quill refuses to hold a blob: URL, and that refusal is the
 * whole reason a pasted picture never reached the saved description.
 *
 * The Image blot rewrites its own src on create (quill/formats/image.js):
 *
 *     static sanitize(url) {
 *         return sanitize(url, ['http', 'https', 'data']) ? url : '//:0';
 *     }
 *
 * and that helper reads the protocol off an <a href>. For the blob: URL this
 * file hands it, the protocol is "blob" — not on the list — so the DOM node is
 * born as <img src="//:0"> and the blob string never exists in the document at
 * all. Which meant the two swap steps in sync() below were searching the HTML
 * for a substring that was never in it: dead code that looked correct.
 *
 * What got saved was <img src="//:0">, and the server finished the job —
 * HTMLPurifier's Core.RemoveInvalidImg drops an img whose src won't parse, then
 * AutoFormat.RemoveEmpty drops the paragraph it left behind. The file was in
 * المرفقات, the text was saved, and only the picture was gone. Exactly the
 * reported symptom, and it had nothing to do with the upload pipeline.
 *
 * So blob: is allowed through, and ONLY blob:. Everything else still goes to
 * Quill's own check, which is what keeps javascript: out. This is not a
 * security boundary either way: the blob is same-origin, dies with the tab, and
 * never leaves the browser — sync() swaps it for the placeholder before submit,
 * and the server's whitelist is http/https/mailto regardless of what arrives.
 */
const BaseImage = Quill.import('formats/image');

class InlineImage extends BaseImage {
    static sanitize(url) {
        return String(url).startsWith('blob:') ? url : super.sanitize(url);
    }
}

Quill.register(InlineImage, true);

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
         * Where to put the picture. Never allowed to throw.
         *
         * ★ (2026-08-03) handOff used to end its fallback with
         * `this.quill.getSelection(true)`. That runs getSelection → update →
         * getRange → normalizedToRange, and the last step maps each selection
         * node back to a blot. During a paste the node it lands on is not
         * always one Quill owns, so the map yields null and it dies on
         * `null.offset`. It THROWS rather than returning null, so the `?.` that
         * used to guard it never ran.
         *
         * ★ (2026-08-04) That fix dropped the `true` and called this the cure.
         * It is not: quill/core/quill.js:316 is
         *
         *     getSelection(focus = false) {
         *         if (focus) this.focus();
         *         this.update();                     // <- unconditional
         *         return this.selection.getRange()[0];
         *     }
         *
         * The argument only skips focus(). update() → getRange() →
         * normalizedToRange runs either way, so the bare call throws on exactly
         * the same input. Watched it throw in a real headless Chrome, from the
         * paste handler, on a first paste into an empty editor.
         *
         * Hence the catch, and hence the callers no longer read the selection
         * themselves — there is one guarded way to ask this question.
         */
        caret() {
            try {
                return this.quill.getSelection()?.index ?? this.quill.getLength();
            } catch {
                return this.quill.getLength();
            }
        },

        /**
         * Copies the editor's content into the hidden input the form posts.
         *
         * ★ (2026-08-04) A method rather than a closure inside init(), because
         * handOff has to be able to call it directly.
         *
         * It runs on Quill's text-change, and for typing that is enough. For a
         * pasted image it is not: the insert emits text-change through Quill's
         * own emitter, and Quill's internal selection listener is registered
         * BEFORE this one. When that listener throws — which it does whenever
         * the caret sits on a node the scroll cannot map (see caret()) — the
         * emit unwinds and every later listener is skipped, this one included.
         *
         * The failure that produced was silent and specific: the picture was on
         * screen, the file was in المرفقات, and the input still held the old
         * HTML, so the form posted a description with no image in it. It looked
         * exactly like "the image was not saved" — because it wasn't.
         *
         * Being callable means the paste path does not have to win that race.
         */
        sync() {
            let html = this.quill.getSemanticHTML();

            // What's on screen is a blob: URL — meaningless to anyone else and
            // dead the moment this tab closes. What gets submitted is the
            // placeholder, which the server turns into the real attachment URL.
            // Any blob with no token left (image dragged in from another tab,
            // say) is dropped rather than saved as a broken link.
            this.pending.forEach((token, blob) => {
                html = html.split(blob).join(pendingUrl(token));
            });

            html = html.replace(/<img[^>]*src="blob:[^"]*"[^>]*>/g, '');

            this.$refs.input.value =
                this.quill.getText().trim() === '' && ! html.includes('<img') ? '' : html;
        },

        handOff(files) {
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
                let at = this.caret();

                /**
                 * ★ (2026-08-04) The range is dropped BEFORE inserting, and
                 * every word of this is measured rather than reasoned.
                 *
                 * insertEmbed runs modify() (quill/core/quill.js:529), which is
                 *
                 *     let range = index == null ? null : this.getSelection();
                 *     const change = modifier();          // the actual insert
                 *     if (range != null) { ...; this.setSelection(range, SILENT); }
                 *     if (change.length() > 0) { ...emit TEXT_CHANGE... }
                 *
                 * Both selection calls map DOM nodes back to blots and then hit
                 * blot.offset() with no null check (core/selection.js:211), so a
                 * caret on a node the scroll does not own kills the call. And
                 * the dangerous one is the SECOND: by then the image is already
                 * in the document, so the throw leaves the insert done but skips
                 * the TEXT_CHANGE emit — which is what drives sync(). The
                 * picture appears on screen and the hidden input never learns
                 * about it, so the form submits the old description. Watched
                 * exactly that on the edit page: two blob images in the editor,
                 * the input still holding the original HTML.
                 *
                 * (Retrying on the exception is worse, not better — modifier()
                 * has already run, so the retry inserts a SECOND copy.)
                 *
                 * With no range at all, getNativeRange bails on rangeCount <= 0
                 * (selection.js:182), so `range` is null, the setSelection
                 * branch is skipped entirely, and TEXT_CHANGE always fires.
                 * Nothing is left that can throw between the insert and sync().
                 */
                /**
                 * ★ (2026-08-05) The native range is NOT cleared here any more.
                 *
                 * It used to be, to stop insertEmbed dying on a caret Quill
                 * could not map. The normalizedToRange guard installed in init()
                 * now turns that same failure into a null selection, which
                 * modify() already handles — so the clearing bought nothing and
                 * cost the thing that matters: removeAllRanges() takes focus off
                 * the contenteditable, and Quill's setNativeRange then refuses
                 * to install a new range at all. Measured: rangeCount stayed 0
                 * however the caret was set afterwards, so the editor was left
                 * focused-looking and untypable.
                 */
                accepted.forEach((token) => {
                    const blob = URL.createObjectURL(files[tokens.indexOf(token)]);
                    this.pending.set(blob, token);
                    this.quill.insertEmbed(at, 'image', blob, 'user');
                    at += 1;
                });

                /**
                 * ★ (2026-08-05) The caret goes immediately AFTER the picture,
                 * and no blank line is manufactured for it.
                 *
                 * The obvious move — insertText(at, '\n') so there is a line
                 * waiting below — produces a paragraph Quill does not consider
                 * real: an empty block with no Break blot in it, so
                 * scroll.leaf(index) returns null for every position inside it.
                 * That matters because setNativeRange answers a null node by
                 * calling root.blur() (quill/core/selection.js), so the call
                 * meant to place the cursor was the call that made the editor
                 * untypable. Measured leaf-by-leaf: text·text·text·text·image·
                 * image·null — the line existed on screen and not in the model,
                 * and scroll.optimize() did not repair it.
                 *
                 * Landing right after the image is a position Quill does own,
                 * and Enter from there makes a proper paragraph the normal way —
                 * which is what was asked for, minus the broken block.
                 */

                /**
                 * The caret, put back one frame later. Doing it synchronously
                 * races Quill's own MutationObserver: it rebuilds the blot tree
                 * after the insert, and a native range installed before that
                 * points at nodes which no longer exist by the time Quill's
                 * selectionchange handler reads it — an uncaught throw on every
                 * paste, in a stack we are not part of. After a frame the tree
                 * has settled and the index maps cleanly.
                 */
                // Explicitly, not via the text-change the insert emits — see
                // sync(). If Quill's own listener throws on the way there, this
                // is what keeps the form's copy of the description correct.
                this.sync();

                /**
                 * Put the caret on that new line, and put the focus back.
                 *
                 * ★ (2026-08-05) setTimeout, not requestAnimationFrame. rAF does
                 * not run while the page is hidden, so pasting and switching tab
                 * left the caret unset and the editor unfocused — you came back
                 * to a description you could not type into. Watched it happen in
                 * a headless (never-painted) browser, which is the same
                 * condition. A macrotask still gives Quill's MutationObserver
                 * the tick it needs, and it always fires.
                 *
                 * root.focus() rather than quill.focus(): the latter restores
                 * Quill's *saved* range, which is the one we just cleared, so it
                 * puts the cursor back exactly where we did not want it. Focus
                 * the element, then say where the caret goes.
                 */
                const caretAt = at;

                setTimeout(() => {
                    try {
                        // Clamped: an index past the end makes rangeToNative
                        // return null, and setNativeRange answers null by
                        // calling root.blur() (quill/core/selection.js). That is
                        // how "paste then type" ended up typing nowhere — the
                        // editor was blurred by the very call meant to put the
                        // cursor in it. Measured: focus was on .ql-editor
                        // immediately after the paste and BODY one tick later.
                        const end = Math.max(0, this.quill.getLength() - 1);
                        const target = Math.min(caretAt, end);

                        this.quill.setSelection(target, 0, 'silent');

                        // And if it blurred anyway, put it back and try once
                        // more — now with focus, setNativeRange takes.
                        if (document.activeElement !== this.quill.root) {
                            this.quill.root.focus({ preventScroll: true });
                            this.quill.setSelection(target, 0, 'silent');
                        }
                    } catch {
                        // The images are in and submitted; the caret is cosmetic.
                    }
                }, 0);
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

            /**
             * ★ (2026-08-04) Quill's selection reader is missing a null check,
             * and it throws from places no caller can catch.
             *
             * normalizedToRange (quill/core/selection.js:204-213) maps each node
             * of the native range back to a blot and uses the result straight
             * away:
             *
             *     const blot = this.scroll.find(node, true);
             *     const index = blot.offset(this.scroll);   // blot may be null
             *
             * find() returns null for any node the scroll does not own, and a
             * browser will happily leave the caret on such a node — after a
             * paste, after Quill rebuilds a line, after anything that edits the
             * DOM near the cursor. The read then dies on `null.offset`.
             *
             * The reason a try/catch at the call site is not enough: Quill also
             * calls this from a selectionchange handler wrapped in
             * setTimeout(update, 1) and from its MutationObserver, neither of
             * which is inside any stack of ours. Measured six uncaught
             * exceptions per page load on a ticket whose editor opens with
             * content — identical before and after the inline-image work, so it
             * is Quill's, not ours.
             *
             * null is the answer Quill already understands: getRange() returns
             * [null, null] for "nothing usable" and every caller reads that as
             * "no caret". Patched per instance rather than on the prototype so
             * it cannot leak into anything else that imports Quill.
             */
            const selection = this.quill.selection;
            const normalizedToRange = selection.normalizedToRange.bind(selection);

            selection.normalizedToRange = (range) => {
                try {
                    return normalizedToRange(range);
                } catch {
                    return null;
                }
            };

            // Capture phase: Quill binds its own paste/drop handlers on the same
            // element, and the base64 insert has to be stopped before they run.
            /**
             * ★ (2026-08-04) preventDefault FIRST, before anything that can
             * throw. This is the difference between a working paste and a 2MB
             * base64 blob in a LONGTEXT column.
             *
             * The paste handler used to read the caret first and cancel the
             * event second. When that read threw (see caret()), the handler
             * died before ever reaching preventDefault — so Quill's own paste
             * handler, which this capture-phase listener exists purely to
             * outrun, went ahead and inlined the image as a data: URI. The
             * upload pipeline was bypassed entirely: no finfo, no re-encode, no
             * size cap, no thumbnail. Every defence in § 5, skipped by an
             * exception in a line that was only trying to find the cursor.
             *
             * Once the event is cancelled nothing later can undo it, so the
             * order — not the guard — is what makes this safe.
             */
            const intercept = (event, transfer) => {
                const images = imagesFrom(transfer);

                if (! images.length) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                // Cancelling does not move the caret, so reading it here is the
                // same answer, minus the chance of losing the whole handler.
                this.handOff(images);
            };

            this.quill.root.addEventListener(
                'paste', (event) => intercept(event, event.clipboardData), true
            );

            this.quill.root.addEventListener(
                'drop', (event) => intercept(event, event.dataTransfer), true
            );

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

            this.quill.on('text-change', () => this.sync());
            this.sync();
        },
    };
}
