// Arabic source string -> the string for the current UI language.
//
// Same contract as __() on the server: the Arabic text is the key, and a
// missing translation falls back to it. The layout fills window.__i18n with
// only the entries JS uses, and leaves it empty in the Arabic UI.
export function t(text, params = {}) {
    const dictionary = window.__i18n || {};
    let out = dictionary[text] ?? text;

    for (const [key, value] of Object.entries(params)) {
        out = out.replaceAll(`:${key}`, String(value));
    }

    return out;
}
