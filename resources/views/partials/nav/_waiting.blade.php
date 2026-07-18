{{-- Things waiting on this person specifically. --}}
@can('tickets.resolve')
    <a href="{{ route('queues.testing') }}" class="nav__link" title="طابور التيست"
       @if (request()->routeIs('queues.testing')) aria-current="page" @endif>
        <x-icon name="check-circle" class="nav__icon" />
        <span class="nav__label">طابور التيست</span>
    </a>
@endcan

@can('features.approve')
    <a href="{{ route('queues.approvals') }}" class="nav__link" title="الموافقات"
       @if (request()->routeIs('queues.approvals')) aria-current="page" @endif>
        <x-icon name="hourglass" class="nav__icon" />
        <span class="nav__label">الموافقات</span>
    </a>
@endcan
