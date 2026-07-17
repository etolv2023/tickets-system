@php
    $user = auth()->user();
@endphp

<nav class="nav" aria-label="التنقل الرئيسي">
    <a href="{{ route('home') }}" class="nav__brand">
        @if ($appLogo)
            <img src="{{ Storage::url($appLogo) }}" alt="" height="24">
        @else
            <span class="nav__brand-mark">{{ mb_substr($appName, 0, 1) }}</span>
        @endif
        <span>{{ $appName }}</span>
    </a>

    <div class="nav__list">
        @include('partials.nav._work')
        @include('partials.nav._queues')
        @include('partials.nav._admin')
    </div>

    <div class="nav__foot">
        <div class="nav__user">
            <x-avatar :user="$user" />
            <div class="spacer">
                <div class="nav__user-name">{{ $user->name }}</div>
                <div class="nav__user-role">{{ $user->role->name_ar }}</div>
            </div>

            {{-- F20: the bell, with the unread count. --}}
            <a href="{{ route('notifications.index') }}" class="bell" aria-label="الإشعارات">
                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 2a6 6 0 00-6 6v3.6l-1.4 2.1A1 1 0 003.4 15h13.2a1 1 0 00.8-1.3L16 11.6V8a6 6 0 00-6-6zM7.5 16.5a2.5 2.5 0 005 0h-5z" />
                </svg>
                @if ($unreadCount > 0)
                    <span class="bell__count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
                @endif
            </a>
        </div>

        <div class="row">
            <button type="button" class="btn btn--ghost btn--sm" x-data @click="$store.theme.toggle()">
                <svg class="btn__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm5 8a5 5 0 11-10 0 5 5 0 0110 0zm-.7 5.7a1 1 0 011.4-1.4l.7.7a1 1 0 01-1.4 1.4l-.7-.7zM17 9a1 1 0 100 2h1a1 1 0 100-2h-1zm-1.7-4.3a1 1 0 011.4 1.4l-.7.7a1 1 0 11-1.4-1.4l.7-.7zM10 16a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM4.7 14.3a1 1 0 011.4 1.4l-.7.7a1 1 0 01-1.4-1.4l.7-.7zM3 9a1 1 0 000 2H2a1 1 0 100-2h1zm1.7-4.3l.7.7a1 1 0 001.4-1.4l-.7-.7a1 1 0 00-1.4 1.4z" />
                </svg>
                المظهر
            </button>

            <form method="POST" action="{{ route('logout') }}" class="spacer">
                @csrf
                <x-button variant="ghost" size="sm" class="btn--block">خروج</x-button>
            </form>
        </div>
    </div>
</nav>
