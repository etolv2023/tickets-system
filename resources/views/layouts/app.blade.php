<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'الرئيسية') — {{ $appName }}</title>
    <link rel="icon" href="{{ $appLogo ? Storage::url($appLogo) : '/favicon.ico' }}">

    {{-- Applies the saved theme before first paint. Without it the page flashes
         light before Alpine boots and corrects it. --}}
    <script>
        (function () {
            var saved = localStorage.getItem('theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.dataset.theme = saved;
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

        <div class="shell__main">
            <main class="shell__body">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
