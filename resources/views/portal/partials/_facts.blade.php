{{-- The four things a client opens this page to find out. They were nowhere on
     the screen before: you got a title, a status pill, and a form — nothing
     saying when you reported it, whether anyone had answered, or where it got
     to. Everything internal (assignees, priority, SLA, points) stays off.

     Its own block rather than the staff .facts panel: that one is a vertical
     label/value list inside a sidebar card, this is a strip across the top. --}}
<dl class="portal-facts">
    <div class="portal-facts__item">
        <dt class="portal-facts__key">الحالة</dt>
        <dd class="portal-facts__value">
            <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
        </dd>
    </div>

    <div class="portal-facts__item">
        <dt class="portal-facts__key">اتفتحت</dt>
        <dd class="portal-facts__value u-nums">{{ $ticket->reported_at->translatedFormat('j F Y') }}</dd>
    </div>

    <div class="portal-facts__item">
        <dt class="portal-facts__key">آخر رد</dt>
        <dd class="portal-facts__value u-nums">
            {{ $lastReply?->created_at->diffForHumans() ?? 'لسه مفيش' }}
        </dd>
    </div>

    @if ($ticket->resolved_at)
        <div class="portal-facts__item">
            <dt class="portal-facts__key">اتحلت</dt>
            <dd class="portal-facts__value u-nums">{{ $ticket->resolved_at->translatedFormat('j F Y') }}</dd>
        </div>
    @else
        <div class="portal-facts__item">
            <dt class="portal-facts__key">عدد الرسائل</dt>
            <dd class="portal-facts__value u-nums">{{ $comments->count() }}</dd>
        </div>
    @endif
</dl>
