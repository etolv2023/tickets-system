{{-- Things waiting on this person specifically. --}}
{{-- ★ (2026-08-29) F30. Ungated on purpose — the list is already scoped to what
     each person can see, and "شغلي خلص ومحدش قفل التذكرة" is a question every
     role has. It is also what keeps this group from rendering empty for someone
     who holds neither tickets.resolve nor features.approve. --}}
<a href="{{ route('queues.ready') }}" class="nav__link" title="جاهزة للقفل"
   @if (request()->routeIs('queues.ready')) aria-current="page" @endif>
    <x-icon name="check" class="nav__icon" />
    <span class="nav__label">جاهزة للقفل</span>
</a>

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
