<div class="portal-thread">
    @forelse ($comments as $comment)
        @php $fromClient = $comment->contact_id !== null; @endphp
        <div @class(['portal-comment', 'portal-comment--staff' => ! $fromClient])>
            <span class="portal-comment__avatar">
                @if ($fromClient)
                    <x-icon name="contact" size="1.1em" />
                @else
                    <x-avatar :user="$comment->user" size="sm" />
                @endif
            </span>

            <div class="portal-comment__bubble">
                <div class="portal-comment__head">
                    <span class="portal-comment__who">
                        {{ $comment->authorName() }}
                        @if ($fromClient)
                            <span class="portal-comment__badge">أنت</span>
                        @else
                            <span class="portal-comment__badge portal-comment__badge--team">فريق الدعم</span>
                        @endif
                    </span>
                    <time datetime="{{ $comment->created_at->toIso8601String() }}">
                        {{ $comment->created_at->diffForHumans() }}
                    </time>
                </div>
                <div class="prose">{!! $comment->body !!}</div>

                @if ($comment->attachments->isNotEmpty())
                    <ul class="portal-files">
                        @foreach ($comment->attachments as $file)
                            <li>
                                <a class="portal-file"
                                   href="{{ route('portal.attachment', [$ticket->ticket_number, $file]) }}">
                                    @if ($file->isImage() && $file->thumbnail_name)
                                        <img class="portal-file__thumb" loading="lazy" alt=""
                                             src="{{ route('portal.attachment.thumb', [$ticket->ticket_number, $file]) }}">
                                    @else
                                        <span class="portal-file__icon"><x-icon name="download" /></span>
                                    @endif

                                    <span class="portal-file__meta">
                                        <span class="portal-file__name">{{ $file->original_name }}</span>
                                        <span class="portal-file__size u-mono">{{ $file->sizeLabel() }}</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @empty
        {{-- "مفيش رسائل لسه" on its own reads as a dead end. It is not one —
             the team has the ticket, and the box below is how you reach them. --}}
        <div class="blank">
            <h2 class="blank__title">لسه مفيش رسائل</h2>
            <p class="blank__body">
                التذكرة وصلت لفريق الدعم وهم شايفينها. أول ما يبقى في رد هيظهر هنا،
                وتقدر تكتب أي تفاصيل زيادة في الخانة اللي تحت.
            </p>
        </div>
    @endforelse
</div>
