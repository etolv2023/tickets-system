<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ $appName }}</title>
    {{-- The uploaded logo wins; the SVG below is the default mark. The type
         hint is only emitted for the SVG, because an uploaded logo may be a
         PNG and a wrong MIME hint on rel=icon is worse than none. --}}
    @if ($appLogo)
        <link rel="icon" href="{{ Storage::url($appLogo) }}">
    @else
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    @endif

    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.dataset.theme = saved;
            }
        })();
    </script>

    <link rel="preload" href="{{ asset('fonts/cairo-arabic.woff2') }}" as="font" type="font/woff2" crossorigin>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="portal">
        <header class="portal__head">
            <div class="portal__head-inner">
                <div class="auth__brand">
                    @if ($appLogo)
                        <img src="{{ Storage::url($appLogo) }}" alt="{{ $appName }}" height="24">
                    @else
                        <span class="nav__brand-mark">{{ mb_substr($appName, 0, 1) }}</span>
                    @endif
                    <span>{{ $appName }}</span>
                </div>

                {{-- The portal has no nav and no topbar, so this is the only
                     control on the page — and without it the one screen a
                     customer actually sees was stuck on whatever theme their
                     OS asked for, with no way out. --}}
                <x-theme-toggle />
            </div>
        </header>

        <main class="portal__body">
            @yield('content')
        </main>
    </div>
</body>
</html>
