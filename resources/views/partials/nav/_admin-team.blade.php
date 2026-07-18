{{-- The people doing the work: who they are, what they can do, when they're off. --}}
@can('users.manage')
    <a href="{{ route('admin.users.index') }}" class="nav__link" title="المستخدمين"
       @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>
        <x-icon name="users" class="nav__icon" />
        <span class="nav__label">المستخدمين</span>
    </a>

    <a href="{{ route('admin.roles.index') }}" class="nav__link" title="الأدوار والصلاحيات"
       @if (request()->routeIs('admin.roles.*')) aria-current="page" @endif>
        <x-icon name="shield" class="nav__icon" />
        <span class="nav__label">الأدوار والصلاحيات</span>
    </a>

    <a href="{{ route('admin.calendar.index') }}" class="nav__link" title="العطلات والإجازات"
       @if (request()->routeIs('admin.calendar.*')) aria-current="page" @endif>
        <x-icon name="calendar" class="nav__icon" />
        <span class="nav__label">العطلات والإجازات</span>
    </a>
@endcan
