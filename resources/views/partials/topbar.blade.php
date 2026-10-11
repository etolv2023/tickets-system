{{--
    Topbar: the current screen's name on one side, global controls on the other.
    It stays put while the body scrolls under it.

    The title comes from each screen's @section('title'), so there is exactly one
    place a screen names itself.
--}}

<header class="topbar">
    {{-- One control, two jobs: on a desktop it collapses the rail to icons, on
         a phone it opens the nav drawer over the page. aria-expanded has to
         report whichever of the two is actually in play, or a screen reader is
         told the menu is open while it is off-screen. --}}
    <button type="button" class="topbar__toggle tooltip--below" x-data
            @click="$store.sidebar.toggle()"
            :aria-expanded="($store.sidebar.isMobile ? $store.sidebar.open : !$store.sidebar.collapsed).toString()"
            aria-controls="app-nav"
            aria-label="{{ __('ui.common.sidebar') }}" data-tooltip="{{ __('ui.common.sidebar') }}">
        <x-icon name="sidebar" />
    </button>

    <h1 class="topbar__title">@yield('title', __('ui.common.home'))</h1>

    <div class="spacer"></div>

    <form method="POST" action="{{ route('locale.update') }}">
        @csrf
        <input type="hidden" name="locale" value="{{ __('ui.language_code') }}">
        <button type="submit" class="topbar__btn" aria-label="{{ __('ui.language') }}" title="{{ __('ui.language') }}">
            <span class="u-mono">{{ app()->isLocale('ar') ? 'EN' : 'AR' }}</span>
        </button>
    </form>

    <form method="GET" action="{{ route('search') }}" class="topbar__search" role="search">
        <x-icon name="search" class="topbar__search-icon" />
        <input type="search" name="q" value="{{ request('q') }}"
               placeholder="{{ __('ui.common.search_placeholder') }}" aria-label="{{ __('ui.common.search') }}"
               aria-keyshortcuts="Control+K Meta+K /">
        <kbd class="topbar__search-key" aria-hidden="true">⌘K</kbd>
    </form>

    <x-theme-toggle />

    {{-- ★ (2026-08-29) ملء الشاشة, next to the bell. Placed with the other
         viewport controls (the rail toggle is the same idea at the other end of
         the bar) rather than in a menu: it is one click, and the whole point of
         it is that you reach for it when the screen feels cramped. --}}
    <x-fullscreen-toggle />

    {{-- F20: the bell. The count refreshes on its own so it does not sit stale
         until the next page load. --}}
    <a href="{{ route('notifications.index') }}" class="topbar__btn bell tooltip--end tooltip--below"
       x-data="bell({{ (int) $unreadCount }})"
       :aria-label="count > 0 ? `{{ __('ui.common.notifications') }} — ${label}` : '{{ __('ui.common.notifications') }}'"
       aria-label="{{ __('ui.common.notifications') }}" data-tooltip="{{ __('ui.common.notifications') }}">
        <x-icon name="bell" />
        <span class="bell__count" x-show="count > 0" x-cloak x-text="label"></span>
    </a>
</header>
