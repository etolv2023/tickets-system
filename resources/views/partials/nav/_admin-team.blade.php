{{-- The people doing the work: who they are, what they can do, when they're off. --}}
@can('users.manage')
    <a href="{{ route('admin.users.index') }}" class="nav__link" title="{{ __('ui.nav.users') }}"
       @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>
        <x-icon name="users" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.users') }}</span>
    </a>

    <a href="{{ route('admin.roles.index') }}" class="nav__link" title="{{ __('ui.nav.roles_permissions') }}"
       @if (request()->routeIs('admin.roles.*')) aria-current="page" @endif>
        <x-icon name="shield" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.roles_permissions') }}</span>
    </a>

    <a href="{{ route('admin.calendar.index') }}" class="nav__link" title="{{ __('ui.nav.holidays') }}"
       @if (request()->routeIs('admin.calendar.*')) aria-current="page" @endif>
        <x-icon name="calendar" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.holidays') }}</span>
    </a>
@endcan
