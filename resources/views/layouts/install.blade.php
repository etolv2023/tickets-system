<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') — تنصيب النظام</title>

    {{-- No settings lookup anywhere in this layout: at this point there is no
         database to read a system name or logo from. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="install">
        <div class="install__box">
            <div class="install__brand">
                <span class="nav__brand-mark">ت</span>
                <span>تنصيب النظام</span>
            </div>

            @include('install.partials._steps', ['current' => $step])

            <div class="card">
                <div class="card__header">
                    <h1 class="card__title">@yield('title')</h1>
                </div>
                <div class="card__body">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</body>
</html>
