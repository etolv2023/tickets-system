<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — {{ $appName }}</title>
    <link rel="icon" href="{{ $appLogo ? Storage::url($appLogo) : '/favicon.ico' }}">

    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.dataset.theme = saved;
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="auth">
        <div class="auth__box">
            <div class="auth__brand">
                @if ($appLogo)
                    <img src="{{ Storage::url($appLogo) }}" alt="{{ $appName }}" height="28">
                @else
                    <span class="nav__brand-mark">{{ mb_substr($appName, 0, 1) }}</span>
                @endif
                <span>{{ $appName }}</span>
            </div>

            @yield('content')

            <p class="auth__foot">@yield('foot')</p>
        </div>
    </div>
</body>
</html>
