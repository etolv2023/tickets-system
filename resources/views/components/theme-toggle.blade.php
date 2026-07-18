{{--
    Light / dark toggle.

    Both glyphs ship and CSS shows the one matching the active theme, rather
    than Alpine picking. That way the right icon is painted on the first frame,
    before any JavaScript has run — the inline script in the layout head has
    already set data-theme by then.

    Extracted from the topbar so the client portal can carry it too: the portal
    has no topbar and no nav, so without this it was the one screen in the
    system with no way to change the theme.
--}}

<button type="button" {{ $attributes->class('topbar__btn topbar__theme') }} x-data
        @click="$store.theme.toggle()"
        aria-label="تبديل المظهر" title="تبديل المظهر">
    <x-icon name="moon" class="topbar__theme-light" />
    <x-icon name="sun" class="topbar__theme-dark" />
</button>
