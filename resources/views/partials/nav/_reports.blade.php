{{-- Management-facing: aggregate numbers you go to on purpose, not your own. --}}
@can('points.view.all')
    <a href="{{ route('reports.points') }}" class="nav__link" title="تقرير النقاط"
       @if (request()->routeIs('reports.points')) aria-current="page" @endif>
        <x-icon name="chart" class="nav__icon" />
        <span class="nav__label">تقرير النقاط</span>
    </a>

    <a href="{{ route('reports.points-detail') }}" class="nav__link" title="تقرير النقاط التفصيلي"
       @if (request()->routeIs('reports.points-detail')) aria-current="page" @endif>
        <x-icon name="activity" class="nav__icon" />
        <span class="nav__label">النقاط التفصيلي</span>
    </a>
@endcan

@can('github.audit')
    <a href="{{ route('github.unmatched-branches') }}" class="nav__link" title="برانشات مخالفة">
        <x-icon name="alert" class="nav__icon" />
        <span class="nav__label">برانشات مخالفة</span>
    </a>
@endcan

@can('reports.view')
    <a href="{{ route('reports.index') }}" class="nav__link" title="التقارير"
       @if (request()->routeIs('reports.index')) aria-current="page" @endif>
        <x-icon name="chart" class="nav__icon" />
        <span class="nav__label">التقارير</span>
    </a>

    <a href="{{ route('reports.team-activity') }}" class="nav__link" title="تقرير التيم التفصيلي"
       @if (request()->routeIs('reports.team-activity')) aria-current="page" @endif>
        <x-icon name="activity" class="nav__icon" />
        <span class="nav__label">تقرير التيم التفصيلي</span>
    </a>

    <a href="{{ route('reports.performance') }}" class="nav__link" title="أداء الموظف"
       @if (request()->routeIs('reports.performance')) aria-current="page" @endif>
        <x-icon name="user" class="nav__icon" />
        <span class="nav__label">أداء الموظف</span>
    </a>

    <a href="{{ route('reports.comparison') }}" class="nav__link" title="مقارنة الأداء"
       @if (request()->routeIs('reports.comparison')) aria-current="page" @endif>
        <x-icon name="users" class="nav__icon" />
        <span class="nav__label">مقارنة الأداء</span>
    </a>

    <a href="{{ route('reports.summary') }}" class="nav__link" title="ملخص التذاكر"
       @if (request()->routeIs('reports.summary')) aria-current="page" @endif>
        <x-icon name="list-checks" class="nav__icon" />
        <span class="nav__label">ملخص التذاكر</span>
    </a>

    <a href="{{ route('reports.aging') }}" class="nav__link" title="أعمار التذاكر"
       @if (request()->routeIs('reports.aging')) aria-current="page" @endif>
        <x-icon name="clock" class="nav__icon" />
        <span class="nav__label">أعمار التذاكر</span>
    </a>

    <a href="{{ route('reports.deadline') }}" class="nav__link" title="المواعيد والـ SLA"
       @if (request()->routeIs('reports.deadline')) aria-current="page" @endif>
        <x-icon name="alert" class="nav__icon" />
        <span class="nav__label">المواعيد والـ SLA</span>
    </a>

    {{-- ★ (2026-10-05) F19.5 — the matrix: people down, ticket types across. --}}
    <a href="{{ route('reports.resolved-by-type') }}" class="nav__link" title="الحلول بالنوع"
       @if (request()->routeIs('reports.resolved-by-type')) aria-current="page" @endif>
        <x-icon name="check-circle" class="nav__icon" />
        <span class="nav__label">الحلول بالنوع</span>
    </a>
@endcan

{{-- ★ (2026-08-29) F27. Sits with the reports because that is what it is: a
     read-only view of the team's finished work, asking one question about it. --}}
@can('github.view')
    <a href="{{ route('github.branches') }}" class="nav__link" title="برانشات التذاكر"
       @if (request()->routeIs('github.branches')) aria-current="page" @endif>
        <x-icon name="git-branch" class="nav__icon" />
        <span class="nav__label">برانشات التذاكر</span>
    </a>
@endcan
