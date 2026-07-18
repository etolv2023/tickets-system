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
@endcan
