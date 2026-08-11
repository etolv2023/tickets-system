import Quill, { Delta, Parchment } from 'quill';

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
 * ★ (2026-08-11) Direction, rewritten as a `dir` attribute with LTR in the
 * whitelist. Quill's own format is neither.
 *
 * quill.js:49 registers 'formats/direction' as DirectionClass, and
 * formats/direction.js declares it
 *
 *     const config = { scope: Scope.BLOCK, whitelist: ['rtl'] };
 *
 * Two things wrong with that here. The class one — ql-direction-rtl — dies at
 * the server: config/purifier.php allows no class attribute, so a direction
 * the user pinned would vanish on save and come back as whatever the heuristic
 * guesses. And a whitelist of ['rtl'] alone cannot say "this line is English":
 * the root is already dir="rtl", so removing the format and setting it are the
 * same picture. LTR has to be a value you can actually store.
 *
 * `dir` survives Purifier (it is on the I18N attribute collection as
 * Enum#ltr,rtl — HTMLModule/Bdo.php:40) once *[dir] is in HTML.Allowed, it is
 * what the browser reads without any CSS of ours, and getSemanticHTML keeps it
 * because convertHTML rebuilds a block from its own outerHTML.
 */
const Direction = new Parchment.Attributor('direction', 'dir', {
    scope: Parchment.Scope.BLOCK,
    whitelist: ['rtl', 'ltr'],
});

Quill.register({ 'formats/direction': Direction }, true);

// Snow ships both arrows already (ui/icons.js:11-12) — the left-to-right one is
// filed under '' because upstream only ever toggles rtl off. The toolbar looks
// icons up by value, so the ltr button needs it under its own key or it renders
// as an empty square.
const icons = Quill.import('ui/icons');

icons.direction.ltr = icons.direction[''];

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
    // Two buttons, three states: pin the line RTL, pin it LTR, or press the lit
    // one again to unpin and hand the line back to the automatic reading in
    // editor.css. Nothing is pinned by default — an Arabic document should not
    // carry a dir attribute on every paragraph just to say what it already is.
    [{ direction: 'rtl' }, { direction: 'ltr' }],
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
 * ★ (2026-08-11) Whether a clipboard carrying BOTH markup and an image file is
 * really an image. Copied from quill/modules/clipboard.js:141-145 on purpose —
 * this is the rule this file was missing, and matching it exactly is the point.
 *
 * Word, Excel, Outlook, Slack and Figma all put a picture of the selection on
 * the clipboard next to the text. imagesFrom() sees that picture, and the
 * handler used to claim the paste on the strength of it: the screenshot went
 * into المرفقات and every word the user copied was thrown away. That is the
 * reported "بيقبل الصور بس لكن التيكست مش بيقبله".
 *
 * A real image paste has no markup at all, or markup that is nothing but the
 * <img>. Anything else is text that happens to travel with a picture.
 */
function htmlIsSingleImage(html) {
    const body = new DOMParser().parseFromString(html, 'text/html').body;

    return body.childElementCount === 1 && body.firstElementChild?.tagName === 'IMG';
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
    /**
     * ★★ (2026-08-11) The Quill instance lives HERE, in the closure, and never
     * on the Alpine component. This is the single most important line in the
     * file: as a component property it was the reason nothing in the toolbar
     * worked.
     *
     * Alpine wraps a component's data in a reactive Proxy, and reading a nested
     * object through it hands you a Proxy of that object too. So `this.quill`
     * was a Proxy, `this.quill.scroll` was a Proxy of the scroll blot — and
     * parchment's lookup ends with an identity check (parchment ScrollBlot):
     *
     *     find(node, bubble = false) {
     *         const blot = this.registry.find(node, bubble);
     *         if (blot == null) return null;
     *         if (blot.scroll === this) return blot;    // <- proxy !== raw
     *         return bubble ? this.find(...) : null;
     *     }
     *
     * `blot.scroll` is the raw scroll; `this` was the Proxy. Never equal, so
     * find() answered null for every node in the document — including the
     * editor's own root. Measured in the running app: find(root) false through
     * the proxy, true through Alpine.raw() on the same instance.
     *
     * Everything downstream followed from that one false. normalizedToRange
     * dies on `null.offset`, so getSelection() returns null, and every Quill
     * entry point that starts by reading the selection quietly does nothing:
     * Bold did nothing, the list buttons threw, and Clipboard.onPaste cancelled
     * the event and returned before pasting. The comments above about "the
     * selection reader throws from places no caller can catch" were all
     * describing this, one symptom at a time.
     *
     * A closure variable is invisible to Alpine, so the instance stays raw. Only
     * `notice` needs to be reactive — it is the one thing the template binds.
     */
    let quill = null;

    /** blob URL -> token, for the swap in sync(). Internal; kept out of Alpine. */
    const pending = new Map();

    let noticeTimer = null;

    return {
        notice: '',

        /**
         * Hands the files to the attachment list and draws each accepted one
         * where the cursor is. The picture stays in the sentence it belongs to;
         * the bytes go through the attachment pipeline. @returns {boolean}
         */
        /**
         * Where to put the picture. Never allowed to throw.
         *
         * ★ (2026-08-03) handOff used to end its fallback with
         * `quill.getSelection(true)`. That runs getSelection → update →
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
            /**
             * ★ (2026-08-05) Clamped to getLength() - 1, and that -1 is the
             * whole fix for "the image went to the bottom and Enter pushes
             * everything down from the top".
             *
             * getLength() counts the document's trailing newline, so it is one
             * PAST the last position you may insert at. An empty editor has
             * length 1, so the old fallback inserted the picture at index 1 —
             * after the final newline — and Quill answered by creating a block
             * for it and leaving an empty paragraph in front. The document came
             * out as <p><br></p><p><img></p> with the caret at 0: cursor
             * stranded above the picture, and every Enter adding another blank
             * line at the top.
             *
             * Missed first time because every test typed something before
             * pasting, which hid it — the fallback only runs when there is no
             * readable selection, and an empty editor is exactly that case.
             */
            const end = Math.max(0, quill.getLength() - 1);

            try {
                const index = quill.getSelection()?.index;

                return index == null ? end : Math.min(index, end);
            } catch {
                return end;
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
            let html = quill.getSemanticHTML();

            // What's on screen is a blob: URL — meaningless to anyone else and
            // dead the moment this tab closes. What gets submitted is the
            // placeholder, which the server turns into the real attachment URL.
            // Any blob with no token left (image dragged in from another tab,
            // say) is dropped rather than saved as a broken link.
            pending.forEach((token, blob) => {
                html = html.split(blob).join(pendingUrl(token));
            });

            html = html.replace(/<img[^>]*src="blob:[^"]*"[^>]*>/g, '');

            this.$refs.input.value =
                quill.getText().trim() === '' && ! html.includes('<img') ? '' : html;
        },

        /**
         * Puts the caret at `index` and the focus back on the editor, one
         * macrotask later. Never allowed to throw.
         *
         * Doing it synchronously races Quill's own MutationObserver: it rebuilds
         * the blot tree after an insert, and a native range installed before
         * that points at nodes which no longer exist by the time Quill's
         * selectionchange handler reads it — an uncaught throw on every paste,
         * in a stack we are not part of. After a tick the tree has settled and
         * the index maps cleanly.
         *
         * ★ (2026-08-05) setTimeout, not requestAnimationFrame. rAF does not run
         * while the page is hidden, so pasting and switching tab left the caret
         * unset and the editor unfocused — you came back to a description you
         * could not type into. Watched it happen in a headless (never-painted)
         * browser, which is the same condition. A macrotask still gives the
         * MutationObserver the tick it needs, and it always fires.
         *
         * root.focus() rather than quill.focus(): the latter restores Quill's
         * own saved range, which is not the position we just worked out. Focus
         * the element, then say where the caret goes.
         */
        placeCaret(index) {
            setTimeout(() => {
                try {
                    // Clamped: an index past the end makes rangeToNative return
                    // null, and setNativeRange answers null by calling
                    // root.blur() (quill/core/selection.js). That is how "paste
                    // then type" ended up typing nowhere — the editor was
                    // blurred by the very call meant to put the cursor in it.
                    // Measured: focus was on .ql-editor immediately after the
                    // paste and BODY one tick later.
                    const end = Math.max(0, quill.getLength() - 1);
                    const target = Math.min(index, end);

                    quill.setSelection(target, 0, 'silent');

                    // And if it blurred anyway, put it back and try once more —
                    // now with focus, setNativeRange takes.
                    if (document.activeElement !== quill.root) {
                        quill.root.focus({ preventScroll: true });
                        quill.setSelection(target, 0, 'silent');
                    }
                } catch {
                    // The content is in and submitted; the caret is cosmetic.
                }
            }, 0);
        },

        /**
         * ★ (2026-08-11) Pasting text is done HERE rather than left to Quill,
         * and that is the fix for "الوصف مش بياخد كوبي بيست".
         *
         * quill/modules/clipboard.js:123-127 is
         *
         *     onCapturePaste(e) {
         *         if (e.defaultPrevented || !quill.isEnabled()) return;
         *         e.preventDefault();
         *         const range = quill.getSelection(true);
         *         if (range == null) return;
         *
         * — the event is cancelled BEFORE the selection is read, and a null
         * answer walks away from an already-dead event. Nothing is pasted, the
         * console stays clean, and it looks like the keystroke never arrived.
         *
         * And null is exactly what that read now returns: getSelection reaches
         * selection.normalizedToRange, which init() wraps to answer null instead
         * of throwing (see there for why it throws — and the note in caret()
         * recording it doing so on a first paste into an empty editor, which is
         * the ticket-create screen). The guard turned a loud failure into a
         * silent one.
         *
         * So the caret is worked out with caret(), which is guarded and clamped
         * and cannot fail, and the delta is applied by hand. Same conversion
         * Quill would have done — clipboard.convert() runs the same matchers, so
         * the data:-URI block installed in init() still holds.
         */
        paste(html, text) {
            const at = this.caret();

            // By index, not by selection: getFormat's default argument is
            // getSelection(true) — the very call this method exists to avoid.
            // Passing a number goes straight to editor.getFormat (quill/core/
            // quill.js). It carries the surrounding formatting, which is what
            // makes a paste inside a code block stay code.
            let formats = {};

            try {
                formats = quill.getFormat(at, 0);
            } catch {
                // Pasting unformatted is a worse paste. Not pasting is no paste.
            }

            const pasted = quill.clipboard.convert({ html, text }, formats);

            quill.updateContents(new Delta().retain(at).concat(pasted), 'user');

            // Explicitly, for the same reason handOff() does it — see sync().
            this.sync();
            this.placeCaret(at + pasted.length());
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
                    pending.set(blob, token);
                    quill.insertEmbed(at, 'image', blob, 'user');
                    at += 1;
                });

                /**
                 * ★ (2026-08-05) A single space after the picture, but ONLY when
                 * the picture would otherwise be the last thing in the document.
                 *
                 * Quill will not hold a caret after a trailing embed: with the
                 * document at [image][\n], asking for index 1 gives you index 0
                 * back, so the cursor sits BEFORE the picture and everything you
                 * type appears above it. That is the reported "بيكتب فوق الصورة".
                 *
                 * The obvious repair — insert a newline so there is a paragraph
                 * below — does not work in this Quill build: the new block comes
                 * out with no Break blot, so scroll.leaf() is null for every
                 * index inside it, and setNativeRange answers a null node by
                 * blurring the editor. Verified leaf-by-leaf, with insertText
                 * and with an atomic Delta, and scroll.optimize() would not
                 * repair it either.
                 *
                 * A space is a real text leaf. The caret can sit on it, typing
                 * continues from it, and because the image is display:block in
                 * the editor the text appears on the line below the picture —
                 * which is what was actually asked for. It costs one invisible
                 * character in the stored HTML.
                 */
                if (at >= quill.getLength() - 1) {
                    quill.insertText(at, ' ', 'user');
                    at += 1;
                }

                /**
                 * The caret goes immediately after that.
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

                // Explicitly, not via the text-change the insert emits — see
                // sync(). If Quill's own listener throws on the way there, this
                // is what keeps the form's copy of the description correct.
                this.sync();

                // Right after the picture — a position Quill owns, so Enter from
                // there makes a proper paragraph the normal way.
                this.placeCaret(at);
            }

            this.notice = ! taken
                ? 'الصور بتتضاف من المرفقات — مفيش مرفقات في الصفحة دي.'
                : accepted.length
                    ? `${accepted.length > 1 ? `${accepted.length} صور اتحطوا` : 'الصورة اتحطت'} هنا وكمان في المرفقات.`
                    : 'الصورة مادخلتش — شوف رسالة المرفقات تحت.';

            clearTimeout(noticeTimer);
            noticeTimer = setTimeout(() => { this.notice = ''; }, 5000);

            return taken;
        },

        init() {
            const holder = this.$refs.editor;

            quill = new Quill(holder, {
                theme: 'snow',
                placeholder,
                modules: {
                    toolbar: {
                        container: simple ? SIMPLE : FULL,
                        handlers: {
                            /**
                             * ★ (2026-08-11) Ours, replacing Quill's built-in
                             * one (modules/toolbar.js, Toolbar.DEFAULTS), which
                             * is wrong for this editor twice over.
                             *
                             * It opens with `const { align } = this.quill
                             * .getFormat()` — no argument, so the default is
                             * getSelection(true), the read that answers null the
                             * moment anything upsets the caret. It then dies on
                             * null.index, which is louder than the silent
                             * no-op but no more useful.
                             *
                             * And it sets `align: right` alongside a right-to-
                             * left direction. That is Quill assuming a left-to-
                             * right document where RTL is the exception. Here it
                             * is the base, alignment already follows the line's
                             * own direction (components/editor.css), and align
                             * is stored as a ql-align-* class which Purifier
                             * drops on the way in — so it would be an invisible
                             * format that survives one save and then does not.
                             *
                             * Toggling is decided from the line itself rather
                             * than from the button's ql-active class, because
                             * that class is painted by Toolbar.update() from the
                             * same selection read and is not there when it is
                             * needed.
                             */
                            direction: (value) => {
                                const at = this.caret();
                                const now = quill.getFormat(at, 0).direction;

                                quill.formatLine(at, 0, 'direction', now === value ? false : value, 'user');
                                this.sync();
                            },
                        },
                    },
                    // Pasting from Word drags in a wall of inline styles. Taking
                    // only the text and the tags we allow keeps that out. F04.1
                    clipboard: { matchVisual: false },
                },
            });

            quill.root.setAttribute('dir', 'rtl');

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
            const selection = quill.selection;
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
            const intercept = (event, transfer, allowText) => {
                const images = imagesFrom(transfer);
                const html = transfer?.getData('text/html') ?? '';
                const text = transfer?.getData('text/plain') ?? '';

                // An image paste is one with no markup beside it, or markup that
                // is only the <img>. Quill's own rule, and the one this handler
                // was missing — see htmlIsSingleImage().
                if (images.length && (! html || htmlIsSingleImage(html))) {
                    event.preventDefault();
                    event.stopPropagation();

                    // Cancelling does not move the caret, so reading it here is
                    // the same answer, minus the chance of losing the whole
                    // handler.
                    this.handOff(images);

                    return;
                }

                // Anything with words in it, we place ourselves. A clipboard
                // carrying both — a Word selection, say — pastes as text and
                // leaves the picture, which is the same call Quill makes.
                if (allowText && (html || text)) {
                    event.preventDefault();
                    event.stopPropagation();

                    this.paste(html, text);
                }

                // Everything else — a non-image file, an empty clipboard — is
                // left alone for Quill to refuse in its own way.
            };

            quill.root.addEventListener(
                'paste', (event) => intercept(event, event.clipboardData, true), true
            );

            // A drop lands where the pointer is, not where the caret is, and
            // caret() cannot know that. Text dropped into the editor stays
            // Quill's job; only the image path is claimed here.
            quill.root.addEventListener(
                'drop', (event) => intercept(event, event.dataTransfer, false), true
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
            quill.clipboard.addMatcher('IMG', (node, delta) => (
                (node.getAttribute?.('src') ?? '').startsWith('data:') ? { ops: [] } : delta
            ));

            if (value) {
                quill.clipboard.dangerouslyPasteHTML(value, 'silent');
            }

            quill.on('text-change', () => this.sync());
            this.sync();
        },
    };
}
