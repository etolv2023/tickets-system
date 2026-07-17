@canany(['settings.manage', 'users.manage', 'companies.manage'])
    <p class="nav__section">الإدارة</p>
@endcanany

@can('companies.manage')
    <a href="{{ route('admin.companies.index') }}" class="nav__link" @if (request()->routeIs('admin.companies.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M4 3a1 1 0 011-1h10a1 1 0 011 1v14a1 1 0 01-1 1h-3v-3H8v3H5a1 1 0 01-1-1V3zm3 2h2v2H7V5zm4 0h2v2h-2V5zM7 9h2v2H7V9zm4 0h2v2h-2V9z" clip-rule="evenodd" />
        </svg>
        الشركات
    </a>

    <a href="{{ route('admin.contacts.index') }}" class="nav__link" @if (request()->routeIs('admin.contacts.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.9 17c.1-.3.1-.7.1-1a6 6 0 00-.9-3.1A5 5 0 0119 17h-6.1zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
        </svg>
        جهات الاتصال
    </a>
@endcan

@can('users.manage')
    <a href="{{ route('admin.users.index') }}" class="nav__link" @if (request()->routeIs('admin.users.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.9 17c.1-.3.1-.7.1-1a6 6 0 00-.9-3.1A5 5 0 0119 17h-6.1zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
        </svg>
        المستخدمين
    </a>
@endcan

@canany(['companies.manage', 'users.manage'])
    <a href="{{ route('admin.import.index') }}" class="nav__link" @if (request()->routeIs('admin.import.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v6.6l2.3-2.3a1 1 0 011.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L9 9.6V3a1 1 0 011-1zM3 14a1 1 0 011 1v1h12v-1a1 1 0 112 0v2a1 1 0 01-1 1H3a1 1 0 01-1-1v-2a1 1 0 011-1z" clip-rule="evenodd" />
        </svg>
        استيراد Excel
    </a>
@endcan

@can('users.manage')
    <a href="{{ route('admin.labels.index') }}" class="nav__link" @if (request()->routeIs('admin.labels.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M17.7 9.3l-7-7A1 1 0 0010 2H3a1 1 0 00-1 1v7a1 1 0 00.3.7l7 7a1 1 0 001.4 0l7-7a1 1 0 000-1.4zM6 7a1 1 0 110-2 1 1 0 010 2z" clip-rule="evenodd" />
        </svg>
        اللابلز
    </a>

    <a href="{{ route('admin.roles.index') }}" class="nav__link" @if (request()->routeIs('admin.roles.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.5 15.5A4.5 4.5 0 016 11h.2a4 4 0 00-.7 2.3v.7H1.5v-.5zM18.5 16h-4v-.7a4 4 0 00-.7-2.3h.2a4.5 4.5 0 014.5 4.5v-1.5zM18 8a2 2 0 11-4 0 2 2 0 014 0zM7 17a3 3 0 016 0v1H7v-1z" />
        </svg>
        الأدوار والصلاحيات
    </a>
@endcan

@can('users.manage')
    <a href="{{ route('admin.calendar.index') }}" class="nav__link" @if (request()->routeIs('admin.calendar.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M6 2a1 1 0 011 1v1h6V3a1 1 0 112 0v1h1a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2h1V3a1 1 0 011-1zM4 8v8h12V8H4zm7.7 1.3a1 1 0 010 1.4l-3 3a1 1 0 01-1.4 0l-1.5-1.5a1 1 0 111.4-1.4l.8.8 2.3-2.3a1 1 0 011.4 0z" clip-rule="evenodd" />
        </svg>
        العطلات والإجازات
    </a>
@endcan

@can('settings.manage')
    <a href="{{ route('admin.settings') }}" class="nav__link" @if (request()->routeIs('admin.settings')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M8.3 2.3a1 1 0 011-.8h1.4a1 1 0 011 .8l.2 1.2a6 6 0 011.3.8l1.2-.5a1 1 0 011.2.4l.7 1.2a1 1 0 01-.2 1.3l-1 .8a6 6 0 010 1.5l1 .8a1 1 0 01.2 1.3l-.7 1.2a1 1 0 01-1.2.4l-1.2-.5a6 6 0 01-1.3.8l-.2 1.2a1 1 0 01-1 .8H9.3a1 1 0 01-1-.8l-.2-1.2a6 6 0 01-1.3-.8l-1.2.5a1 1 0 01-1.2-.4l-.7-1.2a1 1 0 01.2-1.3l1-.8a6 6 0 010-1.5l-1-.8a1 1 0 01-.2-1.3l.7-1.2a1 1 0 011.2-.4l1.2.5a6 6 0 011.3-.8l.2-1.2zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
        </svg>
        الإعدادات
    </a>
@endcan

@can('points.rules.manage')
    <a href="{{ route('admin.point-rules.index') }}" class="nav__link" @if (request()->routeIs('admin.point-rules.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 1l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.4 4.8 17.1l1-5.8L1.5 7.2l5.9-.9L10 1z" />
        </svg>
        مصفوفة النقاط
    </a>
@endcan

@can('audit.view')
    <a href="{{ route('admin.audit.index') }}" class="nav__link" @if (request()->routeIs('admin.audit.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M9 3a6 6 0 104.2 10.3l3.5 3.5a1 1 0 001.4-1.4l-3.5-3.5A6 6 0 009 3zm0 2a4 4 0 110 8 4 4 0 010-8z" clip-rule="evenodd" />
        </svg>
        سجل التدقيق
    </a>
@endcan
