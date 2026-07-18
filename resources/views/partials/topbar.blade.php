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
    <button type="button" class="topbar__toggle" x-data
            @click="$store.sidebar.toggle()"
            :aria-expanded="($store.sidebar.isMobile ? $store.sidebar.open : !$store.sidebar.collapsed).toString()"
            aria-controls="app-nav"
            aria-label="القائمة الجانبية" title="القائمة الجانبية">
        <x-icon name="sidebar" />
    </button>

    <h1 class="topbar__title">@yield('title', 'الرئيسية')</h1>

    <div class="spacer"></div>

    <form method="GET" action="{{ route('search') }}" class="topbar__search" role="search">
        <x-icon name="search" class="topbar__search-icon" />
        <input type="search" name="q" value="{{ request('q') }}"
               placeholder="دوّر برقم أو عنوان أو شركة…" aria-label="بحث عالمي">
    </form>

    <x-theme-toggle />

    {{-- F20: the bell. The count refreshes on its own so it does not sit stale
         until the next page load. --}}
    <a href="{{ route('notifications.index') }}" class="topbar__btn bell"
       x-data="bell({{ (int) $unreadCount }})"
       :aria-label="count > 0 ? `الإشعارات — ${label} جديد` : 'الإشعارات'"
       aria-label="الإشعارات" title="الإشعارات">
        <x-icon name="bell" />
        <span class="bell__count" x-show="count > 0" x-cloak x-text="label"></span>
    </a>
</header>
