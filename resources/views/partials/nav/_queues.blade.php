{{-- Things waiting on this person specifically. --}}
@can('tickets.resolve')
    <a href="{{ route('queues.testing') }}" class="nav__link" @if (request()->routeIs('queues.testing')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd" />
        </svg>
        طابور التيست
    </a>
@endcan

@can('features.approve')
    <a href="{{ route('queues.approvals') }}" class="nav__link" @if (request()->routeIs('queues.approvals')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3a1 1 0 00.3.7l2 2a1 1 0 001.4-1.4L11 9.6V7z" clip-rule="evenodd" />
        </svg>
        الموافقات
    </a>
@endcan

@can('points.view.own')
    <a href="{{ route('reports.my-points') }}" class="nav__link" @if (request()->routeIs('reports.my-points')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M10 1l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.4 4.8 17.1l1-5.8L1.5 7.2l5.9-.9L10 1z" />
        </svg>
        نقاطي
    </a>
@endcan

@can('points.view.all')
    <a href="{{ route('reports.leaderboard') }}" class="nav__link" @if (request()->routeIs('reports.leaderboard')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path d="M3 12h3v6H3v-6zm5.5-5h3v11h-3V7zM14 9h3v9h-3V9z" />
        </svg>
        لوحة الصدارة
    </a>
@endcan

@can('reports.view')
    <a href="{{ route('reports.index') }}" class="nav__link" @if (request()->routeIs('reports.index')) aria-current="page" @endif>
        <svg class="nav__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M4 3a1 1 0 011-1h7l4 4v11a1 1 0 01-1 1H5a1 1 0 01-1-1V3zm3 7h2v5H7v-5zm4-2h2v7h-2V8z" clip-rule="evenodd" />
        </svg>
        التقارير
    </a>
@endcan
