{{-- Personal — what anyone checks about their own points. --}}
@can('points.view.own')
    <a href="{{ route('reports.my-points') }}" class="nav__link" title="نقاطي"
       @if (request()->routeIs('reports.my-points')) aria-current="page" @endif>
        <x-icon name="star" class="nav__icon" />
        <span class="nav__label">نقاطي</span>
    </a>
@endcan

@can('points.view.all')
    <a href="{{ route('reports.leaderboard') }}" class="nav__link" title="لوحة الصدارة"
       @if (request()->routeIs('reports.leaderboard')) aria-current="page" @endif>
        <x-icon name="trophy" class="nav__icon" />
        <span class="nav__label">لوحة الصدارة</span>
    </a>
@endcan
