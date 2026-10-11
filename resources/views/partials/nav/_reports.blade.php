{{-- Management-facing: aggregate numbers you go to on purpose, not your own. --}}
@can('points.view.all')
    <a href="{{ route('reports.points') }}" class="nav__link" title="{{ __('ui.nav.points_report') }}"
       @if (request()->routeIs('reports.points')) aria-current="page" @endif>
        <x-icon name="chart" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.points_report') }}</span>
    </a>

    <a href="{{ route('reports.points-detail') }}" class="nav__link" title="{{ __('ui.nav.points_detail') }}"
       @if (request()->routeIs('reports.points-detail')) aria-current="page" @endif>
        <x-icon name="activity" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.points_detail') }}</span>
    </a>
@endcan

@can('github.audit')
    <a href="{{ route('github.unmatched-branches') }}" class="nav__link" title="{{ __('ui.nav.invalid_branches') }}">
        <x-icon name="alert" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.invalid_branches') }}</span>
    </a>
@endcan

@can('reports.view')
    <a href="{{ route('reports.index') }}" class="nav__link" title="التقارير"
       @if (request()->routeIs('reports.index')) aria-current="page" @endif>
        <x-icon name="chart" class="nav__icon" />
        <span class="nav__label">التقارير</span>
    </a>

    <a href="{{ route('reports.team-activity') }}" class="nav__link" title="{{ __('ui.nav.team_activity') }}"
       @if (request()->routeIs('reports.team-activity')) aria-current="page" @endif>
        <x-icon name="activity" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.team_activity') }}</span>
    </a>

    <a href="{{ route('reports.performance') }}" class="nav__link" title="{{ __('ui.nav.employee_performance') }}"
       @if (request()->routeIs('reports.performance')) aria-current="page" @endif>
        <x-icon name="user" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.employee_performance') }}</span>
    </a>

    <a href="{{ route('reports.comparison') }}" class="nav__link" title="{{ __('ui.nav.performance_comparison') }}"
       @if (request()->routeIs('reports.comparison')) aria-current="page" @endif>
        <x-icon name="users" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.performance_comparison') }}</span>
    </a>

    <a href="{{ route('reports.summary') }}" class="nav__link" title="{{ __('ui.nav.ticket_summary') }}"
       @if (request()->routeIs('reports.summary')) aria-current="page" @endif>
        <x-icon name="list-checks" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.ticket_summary') }}</span>
    </a>

    <a href="{{ route('reports.aging') }}" class="nav__link" title="{{ __('ui.nav.ticket_aging') }}"
       @if (request()->routeIs('reports.aging')) aria-current="page" @endif>
        <x-icon name="clock" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.ticket_aging') }}</span>
    </a>

    <a href="{{ route('reports.deadline') }}" class="nav__link" title="{{ __('ui.nav.deadlines_sla') }}"
       @if (request()->routeIs('reports.deadline')) aria-current="page" @endif>
        <x-icon name="alert" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.deadlines_sla') }}</span>
    </a>

    {{-- ★ (2026-10-05) F19.5 — the matrix: people down, ticket types across. --}}
    <a href="{{ route('reports.resolved-by-type') }}" class="nav__link" title="{{ __('ui.nav.resolutions_by_type') }}"
       @if (request()->routeIs('reports.resolved-by-type')) aria-current="page" @endif>
        <x-icon name="check-circle" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.resolutions_by_type') }}</span>
    </a>
@endcan

{{-- ★ (2026-08-29) F27. Sits with the reports because that is what it is: a
     read-only view of the team's finished work, asking one question about it. --}}
@can('github.view')
    <a href="{{ route('github.branches') }}" class="nav__link" title="{{ __('ui.nav.ticket_branches') }}"
       @if (request()->routeIs('github.branches')) aria-current="page" @endif>
        <x-icon name="git-branch" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.ticket_branches') }}</span>
    </a>
@endcan
