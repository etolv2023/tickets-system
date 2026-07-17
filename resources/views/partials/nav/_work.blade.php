{{-- The screens a person works in every day. --}}
<a href="{{ route('home') }}" class="nav__link" @if (request()->routeIs('home')) aria-current="page" @endif>
    <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path d="M10.7 2.3a1 1 0 00-1.4 0l-7 7A1 1 0 003 11h1v6a1 1 0 001 1h3a1 1 0 001-1v-4h2v4a1 1 0 001 1h3a1 1 0 001-1v-6h1a1 1 0 00.7-1.7l-7-7z" />
    </svg>
    اليوم
</a>

@can('viewAny', App\Models\Ticket::class)
    <a href="{{ route('tickets.index') }}" class="nav__link" @if (request()->routeIs('tickets.*')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v1.5a1.5 1.5 0 000 3V15a2 2 0 01-2 2H5a2 2 0 01-2-2V9.5a1.5 1.5 0 000-3V5zm4 1h6v2H7V6zm0 4h6v2H7v-2z" clip-rule="evenodd" />
        </svg>
        التذاكر
    </a>
@endcan

@can('worklog.manage')
    <a href="{{ route('board.mine') }}" class="nav__link" @if (request()->routeIs('board.mine')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M3 3h4v14H3V3zm5 0h4v9H8V3zm5 0h4v6h-4V3z" />
        </svg>
        بوردي
    </a>
@endcan

@can('tickets.view.all')
    <a href="{{ route('board.team') }}" class="nav__link" @if (request()->routeIs('board.team')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M2 4a1 1 0 011-1h14a1 1 0 011 1v3H2V4zm0 5h5v8H3a1 1 0 01-1-1V9zm7 0h9v7a1 1 0 01-1 1H9V9z" />
        </svg>
        بورد التيم
    </a>
@endcan

<a href="{{ route('calendar.mine') }}" class="nav__link" @if (request()->routeIs('calendar.mine')) aria-current="page" @endif>
    <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M6 2a1 1 0 011 1v1h6V3a1 1 0 112 0v1h1a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2h1V3a1 1 0 011-1zM4 8v8h12V8H4z" clip-rule="evenodd" />
    </svg>
    كاليندري
</a>

@can('reports.view')
    <a href="{{ route('calendar.team') }}" class="nav__link" @if (request()->routeIs('calendar.team')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M7 3a1 1 0 012 0v1h2V3a1 1 0 112 0v1h1a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2h1V3a1 1 0 012 0zM4 8v8h12V8H4zm2 2h3v2H6v-2zm5 0h3v2h-3v-2z" />
        </svg>
        كاليندر التيم
    </a>
@endcan

@can('time.log')
    <a href="{{ route('time.timesheet') }}" class="nav__link" @if (request()->routeIs('time.timesheet')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.3.7l2.5 2.5a1 1 0 001.4-1.4L11 9.6V6z" clip-rule="evenodd" />
        </svg>
        ورقة وقتي
    </a>
@endcan
