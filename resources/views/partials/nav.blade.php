@php
    $user = auth()->user();
@endphp

<nav class="nav" id="app-nav" aria-label="التنقل الرئيسي">
    <a href="{{ route('home') }}" class="nav__brand" title="{{ $appName }}">
        @if ($appLogo)
            <img src="{{ Storage::url($appLogo) }}" alt="" height="24">
        @else
            <span class="nav__brand-mark">{{ mb_substr($appName, 0, 1) }}</span>
        @endif
        <span class="nav__label">{{ $appName }}</span>
    </a>

    {{-- اليوم is the home screen (CLAUDE.md § 6) — it stands alone above every
         folding group rather than hiding as the first link inside "الشغل". --}}
    <a href="{{ route('home') }}" class="nav__link nav__link--home" title="اليوم"
       @if (request()->routeIs('home')) aria-current="page" @endif>
        <x-icon name="home" class="nav__icon" />
        <span class="nav__label">اليوم</span>
    </a>

    {{-- Folding groups, each one topic — the old "الإدارة" alone held 12 links
         under one label, which reads as a dumping ground rather than a menu.
         Splitting it into three (customers, team, system) plus a personal
         "النقاط" apart from the management-only "التقارير" means every group's
         name actually describes everything inside it. --}}
    <div class="nav__list">
        <x-nav-group name="work" title="الشغل" icon="board">
            @include('partials.nav._work')
        </x-nav-group>

        <x-nav-group name="waiting" title="مستنيك" icon="hourglass">
            @include('partials.nav._waiting')
        </x-nav-group>

        @canany(['points.view.own', 'points.view.all'])
            <x-nav-group name="points" title="النقاط" icon="star" :open="false">
                @include('partials.nav._points')
            </x-nav-group>
        @endcanany

        @canany(['points.view.all', 'reports.view'])
            <x-nav-group name="reports" title="التقارير" icon="chart" :open="false">
                @include('partials.nav._reports')
            </x-nav-group>
        @endcanany

        @canany(['companies.manage', 'users.manage'])
            <x-nav-group name="admin-customers" title="العملاء" icon="building" :open="false">
                @include('partials.nav._admin-customers')
            </x-nav-group>
        @endcanany

        @can('users.manage')
            <x-nav-group name="admin-team" title="الفريق" icon="users" :open="false">
                @include('partials.nav._admin-team')
            </x-nav-group>
        @endcan

        @canany(['users.manage', 'settings.manage', 'points.rules.manage', 'audit.view'])
            <x-nav-group name="admin-system" title="النظام" icon="settings" :open="false">
                @include('partials.nav._admin-system')
            </x-nav-group>
        @endcanany
    </div>

    {{-- Footer: who you are, and the one control that belongs to you rather
         than to the app. The bell and the theme toggle live in the topbar. --}}
    <div class="nav__foot">
        <div class="nav__user">
            <x-avatar :user="$user" />

            <div class="nav__user-meta">
                <div class="nav__user-name">{{ $user->name }}</div>
                <div class="nav__user-role">{{ $user->role->name_ar }}</div>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="nav__logout" title="خروج" aria-label="خروج">
                    <x-icon name="logout" />
                </button>
            </form>
        </div>
    </div>
</nav>
