{{--
    ملء الشاشة — hands the whole viewport to the app.

    A component rather than markup inside the topbar, for the same reason
    <x-theme-toggle> is one: the portal and the auth screens have no topbar, and
    if this is ever wanted there it must not be written a second time.

    Unlike the theme toggle, which ships both glyphs and lets CSS pick, this one
    has to ask Alpine: fullscreen is a runtime fact about the browser, not an
    attribute we set on <html> before paint, so there is nothing for CSS to
    match on. x-cloak sits on the "exit" glyph only — the "enter" one is the
    correct first frame in every case, since a page never loads fullscreen.

    Hidden entirely where the API does not exist (iOS Safari fullscreens video
    and nothing else). A button that silently does nothing is worse than no
    button, and x-show costs nothing on the browsers that do support it.
--}}

<button type="button" {{ $attributes->class('topbar__btn tooltip--end tooltip--below') }}
        x-data
        x-show="$store.fullscreen.supported"
        @click="$store.fullscreen.toggle()"
        :aria-label="$store.fullscreen.on ? 'خروج من ملء الشاشة' : 'ملء الشاشة'"
        :data-tooltip="$store.fullscreen.on ? 'خروج من ملء الشاشة' : 'ملء الشاشة'"
        aria-label="ملء الشاشة" data-tooltip="ملء الشاشة">
    <x-icon name="expand" x-show="! $store.fullscreen.on" />
    <x-icon name="shrink" x-show="$store.fullscreen.on" x-cloak />
</button>
