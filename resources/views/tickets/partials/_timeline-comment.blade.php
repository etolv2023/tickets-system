<div class="timeline__item" id="comment-{{ $comment->id }}">
    <div class="timeline__mark">
        <x-avatar :user="$comment->user" />
    </div>

    <div class="timeline__body">
        <div class="timeline__head">
            <span class="timeline__who">{{ $comment->user->name }}</span>
            <time class="timeline__when"
                  datetime="{{ $comment->created_at->toIso8601String() }}"
                  title="{{ $comment->created_at->timezone(config('app.display_timezone'))->translatedFormat('j F Y — H:i') }}">
                {{ $comment->created_at->diffForHumans() }}
            </time>
            @if ($comment->is_internal)
                <x-badge variant="medium">داخلي</x-badge>
            @endif
        </div>

        <div @class(['timeline__content', 'timeline__content--internal' => $comment->is_internal])>
            {{-- Purified before storage (F04.1). Escaping here would show the
                 user their own markup as text. --}}
            <div class="prose">{!! $comment->body !!}</div>

            @if ($comment->attachments->isNotEmpty())
                <hr class="divider">
                <x-attachment-gallery :attachments="$comment->attachments" />
            @endif
        </div>
    </div>
</div>
