{{-- The screens a person works in every day. اليوم itself lives above these
     groups, not in here (see partials.nav) — it's the home screen, not one
     more item in a list.
     title= carries the label for the collapsed rail, where .nav__label is hidden. --}}
@can('viewAny', App\Models\Ticket::class)
    <a href="{{ route('tickets.index') }}" class="nav__link" title="التذاكر"
       @if (request()->routeIs('tickets.*')) aria-current="page" @endif>
        <x-icon name="ticket" class="nav__icon" />
        <span class="nav__label">التذاكر</span>
    </a>
@endcan

@can('worklog.manage')
    <a href="{{ route('board.mine') }}" class="nav__link" title="بوردي"
       @if (request()->routeIs('board.mine')) aria-current="page" @endif>
        <x-icon name="board" class="nav__icon" />
        <span class="nav__label">بوردي</span>
    </a>
@endcan

@can('tickets.view.all')
    <a href="{{ route('board.team') }}" class="nav__link" title="بورد التيم"
       @if (request()->routeIs('board.team')) aria-current="page" @endif>
        <x-icon name="board" class="nav__icon" />
        <span class="nav__label">بورد التيم</span>
    </a>
@endcan

<a href="{{ route('calendar.mine') }}" class="nav__link" title="كاليندري"
   @if (request()->routeIs('calendar.mine')) aria-current="page" @endif>
    <x-icon name="calendar" class="nav__icon" />
    <span class="nav__label">كاليندري</span>
</a>

@can('reports.view')
    <a href="{{ route('calendar.team') }}" class="nav__link" title="كاليندر التيم"
       @if (request()->routeIs('calendar.team')) aria-current="page" @endif>
        <x-icon name="calendar" class="nav__icon" />
        <span class="nav__label">كاليندر التيم</span>
    </a>
@endcan

@can('time.log')
    <a href="{{ route('time.timesheet') }}" class="nav__link" title="ورقة وقتي"
       @if (request()->routeIs('time.timesheet')) aria-current="page" @endif>
        <x-icon name="timer" class="nav__icon" />
        <span class="nav__label">ورقة وقتي</span>
    </a>
@endcan
