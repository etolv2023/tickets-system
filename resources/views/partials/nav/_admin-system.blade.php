{{-- How the system itself is configured, and who's touched what. --}}
@can('users.manage')
    <a href="{{ route('admin.labels.index') }}" class="nav__link" title="اللابلز"
       @if (request()->routeIs('admin.labels.*')) aria-current="page" @endif>
        <x-icon name="tag" class="nav__icon" />
        <span class="nav__label">اللابلز</span>
    </a>
@endcan

@can('settings.manage')
    <a href="{{ route('admin.settings') }}" class="nav__link" title="الإعدادات"
       @if (request()->routeIs('admin.settings')) aria-current="page" @endif>
        <x-icon name="settings" class="nav__icon" />
        <span class="nav__label">الإعدادات</span>
    </a>

    <a href="{{ route('admin.ticket-statuses.index') }}" class="nav__link" title="حالات التذاكر"
       @if (request()->routeIs('admin.ticket-statuses.*')) aria-current="page" @endif>
        <x-icon name="list-checks" class="nav__icon" />
        <span class="nav__label">حالات التذاكر</span>
    </a>

    <a href="{{ route('admin.ticket-types.index') }}" class="nav__link" title="أنواع التذاكر"
       @if (request()->routeIs('admin.ticket-types.*')) aria-current="page" @endif>
        <x-icon name="ticket" class="nav__icon" />
        <span class="nav__label">أنواع التذاكر</span>
    </a>

    <a href="{{ route('admin.priorities.index') }}" class="nav__link" title="الأولويات"
       @if (request()->routeIs('admin.priorities.*')) aria-current="page" @endif>
        <x-icon name="flag" class="nav__icon" />
        <span class="nav__label">الأولويات</span>
    </a>

    <a href="{{ route('admin.subtask-statuses.index') }}" class="nav__link" title="حالات الصب تاسك"
       @if (request()->routeIs('admin.subtask-statuses.*')) aria-current="page" @endif>
        <x-icon name="check-circle" class="nav__icon" />
        <span class="nav__label">حالات الصب تاسك</span>
    </a>

    <a href="{{ route('admin.link-types.index') }}" class="nav__link" title="أنواع الروابط"
       @if (request()->routeIs('admin.link-types.*')) aria-current="page" @endif>
        <x-icon name="link" class="nav__icon" />
        <span class="nav__label">أنواع الروابط</span>
    </a>
@endcan

@can('settings.manage')
    <a href="{{ route('admin.point-values.index') }}" class="nav__link" title="سعر النقطة والمستحقات"
       @if (request()->routeIs('admin.point-values.*')) aria-current="page" @endif>
        <x-icon name="trophy" class="nav__icon" />
        <span class="nav__label">سعر النقطة</span>
    </a>
@endcan

@can('points.rules.manage')
    <a href="{{ route('admin.point-rules.index') }}" class="nav__link" title="تصحيحات النقاط"
       @if (request()->routeIs('admin.point-rules.*')) aria-current="page" @endif>
        <x-icon name="grid" class="nav__icon" />
        <span class="nav__label">تصحيحات النقاط</span>
    </a>
@endcan

@can('audit.view')
    <a href="{{ route('admin.audit.index') }}" class="nav__link" title="سجل التدقيق"
       @if (request()->routeIs('admin.audit.*')) aria-current="page" @endif>
        <x-icon name="audit" class="nav__icon" />
        <span class="nav__label">سجل التدقيق</span>
    </a>
@endcan

@can('system.backup')
    <a href="{{ route('admin.backup') }}" class="nav__link" title="الباك أب"
       @if (request()->routeIs('admin.backup')) aria-current="page" @endif>
        <x-icon name="file" class="nav__icon" />
        <span class="nav__label">الباك أب</span>
    </a>
@endcan
