<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'الرئيسية') — {{ $appName }}</title>
    {{-- The uploaded logo wins; the SVG below is the default mark. The type
         hint is only emitted for the SVG, because an uploaded logo may be a
         PNG and a wrong MIME hint on rel=icon is worse than none. --}}
    @if ($appLogo)
        <link rel="icon" href="{{ Storage::url($appLogo) }}">
    @else
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @endif

    {{-- The Arabic face carries every screen, so it is worth fetching in
         parallel with the stylesheet rather than after it. --}}
    <link rel="preload" href="{{ asset('fonts/cairo-arabic.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- Applies the saved theme and rail state before first paint. Without it the
         page flashes light, and the sidebar flashes open, before Alpine boots
         and corrects them. Both are attributes on <html> for exactly that
         reason — CSS can act on them immediately. --}}
    <script>
        (function () {
            var root = document.documentElement;
            var theme = localStorage.getItem('theme');
            if (theme === 'dark' || theme === 'light') {
                root.dataset.theme = theme;
            }
            if (localStorage.getItem('sidebar') === 'collapsed') {
                root.dataset.sidebar = 'collapsed';
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Screens that drag pull their own bundle. SortableJS has no business on
         the login page (PLAN.md § 8). --}}
    @stack('scripts')
</head>
<body>
    <div class="shell">
        @include('partials.nav')

        {{-- Backdrop for the mobile nav drawer. Only ever visible under
             :root[data-nav-open] below the breakpoint, so on a desktop it is an
             empty div that never paints. Clicking it closes the drawer — the
             other half of "tap outside to dismiss", which every overlay in the
             app already honours. --}}
        <div class="nav-backdrop" x-data @click="$store.sidebar.close()" aria-hidden="true"></div>

        <div class="shell__main">
            @include('partials.topbar')

            <main class="shell__body">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
