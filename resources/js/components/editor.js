import Quill from 'quill';

/**
 * Rich text editor (F04.1). RTL by default, code blocks LTR.
 *
 * Whatever this produces is untrusted: the server runs Purifier over it before
 * storing. Nothing here is a security control — the toolbar is only what the
 * user is *offered*, not what they're limited to.
 */
export default function editor({ name, value = '', placeholder = '' }) {
    return {
        quill: null,

        init() {
            const holder = this.$refs.editor;
            const input = this.$refs.input;

            this.quill = new Quill(holder, {
                theme: 'snow',
                placeholder,
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        [{ header: [3, 4, false] }],
                        ['link', 'blockquote', 'code-block'],
                        ['clean'],
                    ],
                    // Pasting from Word drags in a wall of inline styles. Taking
                    // only the text and the tags we allow keeps that out. F04.1
                    clipboard: { matchVisual: false },
                },
            });

            this.quill.root.setAttribute('dir', 'rtl');

            if (value) {
                this.quill.clipboard.dangerouslyPasteHTML(value, 'silent');
            }

            const sync = () => {
                const html = this.quill.getSemanticHTML();
                input.value = this.quill.getText().trim() === '' && !html.includes('<img') ? '' : html;
            };

            this.quill.on('text-change', sync);
            sync();
        },
    };
}
