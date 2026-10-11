{{-- How the system itself is configured, and who's touched what. --}}
@can('users.manage')
    <a href="{{ route('admin.labels.index') }}" class="nav__link" title="{{ __('ui.nav.labels') }}"
       @if (request()->routeIs('admin.labels.*')) aria-current="page" @endif>
        <x-icon name="tag" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.labels') }}</span>
    </a>
@endcan

@can('settings.manage')
    <a href="{{ route('admin.settings') }}" class="nav__link" title="{{ __('ui.nav.settings') }}"
       @if (request()->routeIs('admin.settings')) aria-current="page" @endif>
        <x-icon name="settings" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.settings') }}</span>
    </a>

    <a href="{{ route('admin.ticket-statuses.index') }}" class="nav__link" title="{{ __('ui.nav.ticket_statuses') }}"
       @if (request()->routeIs('admin.ticket-statuses.*')) aria-current="page" @endif>
        <x-icon name="list-checks" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.ticket_statuses') }}</span>
    </a>

    <a href="{{ route('admin.ticket-types.index') }}" class="nav__link" title="{{ __('ui.nav.ticket_types') }}"
       @if (request()->routeIs('admin.ticket-types.*')) aria-current="page" @endif>
        <x-icon name="ticket" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.ticket_types') }}</span>
    </a>

    <a href="{{ route('admin.priorities.index') }}" class="nav__link" title="{{ __('ui.nav.priorities') }}"
       @if (request()->routeIs('admin.priorities.*')) aria-current="page" @endif>
        <x-icon name="flag" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.priorities') }}</span>
    </a>

    <a href="{{ route('admin.subtask-statuses.index') }}" class="nav__link" title="{{ __('ui.nav.subtask_statuses') }}"
       @if (request()->routeIs('admin.subtask-statuses.*')) aria-current="page" @endif>
        <x-icon name="check-circle" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.subtask_statuses') }}</span>
    </a>

    <a href="{{ route('admin.github.index') }}" class="nav__link" title="GitHub"
       @if (request()->routeIs('admin.github.*')) aria-current="page" @endif>
        <x-icon name="git-branch" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.github') }}</span>
    </a>

    <a href="{{ route('admin.link-types.index') }}" class="nav__link" title="أنواع الروابط"
       @if (request()->routeIs('admin.link-types.*')) aria-current="page" @endif>
        <x-icon name="link" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.link_types') }}</span>
    </a>
@endcan

@can('github.identities.manage')
    <a href="{{ route('admin.github-identities.index') }}" class="nav__link" title="ربط حسابات GitHub"
       @if(request()->routeIs('admin.github-identities.*')) aria-current="page" @endif>
        <x-icon name="user" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.github_accounts') }}</span>
    </a>
@endcan

@can('settings.manage')
    <a href="{{ route('admin.point-values.index') }}" class="nav__link" title="سعر النقطة والمستحقات"
       @if (request()->routeIs('admin.point-values.*')) aria-current="page" @endif>
        <x-icon name="trophy" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.point_value') }}</span>
    </a>
@endcan

@can('points.rules.manage')
    <a href="{{ route('admin.point-rules.index') }}" class="nav__link" title="تصحيحات النقاط"
       @if (request()->routeIs('admin.point-rules.*')) aria-current="page" @endif>
        <x-icon name="grid" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.point_corrections') }}</span>
    </a>
@endcan

@can('audit.view')
    <a href="{{ route('admin.impersonations.index') }}" class="nav__link" title="{{ __('ui.nav.impersonation') }}"
       @if (request()->routeIs('admin.impersonations.*')) aria-current="page" @endif>
        <x-icon name="user" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.impersonation') }}</span>
    </a>

    <a href="{{ route('admin.audit.index') }}" class="nav__link" title="سجل التدقيق"
       @if (request()->routeIs('admin.audit.*')) aria-current="page" @endif>
        <x-icon name="audit" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.audit_log') }}</span>
    </a>
@endcan

@can('schedule.manage')
    <a href="{{ route('admin.scheduled-tasks.index') }}" class="nav__link" title="المهام المجدولة"
       @if (request()->routeIs('admin.scheduled-tasks.*')) aria-current="page" @endif>
        <x-icon name="timer" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.scheduled_tasks') }}</span>
    </a>
@endcan

@can('system.backup')
    <a href="{{ route('admin.backup') }}" class="nav__link" title="{{ __('ui.nav.backup') }}"
       @if (request()->routeIs('admin.backup')) aria-current="page" @endif>
        <x-icon name="file" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.backup') }}</span>
    </a>
@endcan
