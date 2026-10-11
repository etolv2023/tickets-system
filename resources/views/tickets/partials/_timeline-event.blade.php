{{-- A status change. Lighter than a comment on purpose: it's context, not a
     contribution, and the timeline shouldn't shout it. F05 --}}
<div class="timeline__item">
    <div class="timeline__mark">
        <x-avatar :user="$event->user" size="sm" />
    </div>

    <div class="timeline__body">
        <div class="timeline__head">
            <span class="timeline__who">{{ $event->user?->name ?? __('النظام') }}</span>
            <time class="timeline__when"
                  datetime="{{ $event->created_at->toIso8601String() }}"
                  title="{{ $event->created_at->timezone(config('app.display_timezone'))->translatedFormat('j F Y — H:i') }}">
                {{ $event->created_at->diffForHumans() }}
            </time>
        </div>

        <p class="timeline__event">
            @if ($event->from_status === $event->to_status)
                {{ $event->note }}
            @elseif ($event->fromLabel())
                {{ __('نقل التذكرة من «:from» لـ «:to»', ['from' => $event->fromLabel(), 'to' => $event->toLabel()]) }}
            @else
                {{ __('فتح التذكرة') }}
            @endif

            @if ($event->note && $event->from_status !== $event->to_status)
                — {{ $event->note }}
            @endif

            @if ($event->recipientLabel())
                — {{ __('مستنيين رد من :who', ['who' => $event->recipientLabel()]) }}
            @endif
        </p>
    </div>
</div>
