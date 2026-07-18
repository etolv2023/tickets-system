@extends('layouts.app')

@section('title', 'طابور الموافقات')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">مستني قرارك</h1>
                <p class="page-subtitle">فيتشرات وموديولات جديدة مستنية موافقتك قبل ما تتوزّع على المبرمجين.</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        <div class="queue">
            @forelse ($tickets as $ticket)
                <article class="queue-card queue-card--pending" x-data="{ rejecting: false }">
                    <div class="queue-card__badges">
                        <span class="queue-card__number">{{ $ticket->ticket_number }}</span>
                        <x-badge :variant="$ticket->type->variant()" :icon="$ticket->type->icon()" class="badge--sm">{{ $ticket->type->label() }}</x-badge>
                        <x-badge :variant="$ticket->priority->variant()" :icon="$ticket->priority->icon()" class="badge--sm">{{ $ticket->priority->label() }}</x-badge>
                        <x-badge variant="slate" class="badge--sm">{{ $ticket->scope->label() }}</x-badge>
                    </div>

                    <a class="queue-card__title" href="{{ route('tickets.show', $ticket) }}">{{ $ticket->title }}</a>

                    {{-- One plain-text line of the request: an approver should not
                         have to open the ticket to know what they are approving. --}}
                    @if ($excerpt = $ticket->descriptionExcerpt())
                        <p class="queue-card__desc">{{ $excerpt }}</p>
                    @endif

                    <div class="queue-card__meta">
                        <span>{{ $ticket->originLabel() }}</span>
                        <span aria-hidden="true">·</span>
                        <span>طلبها {{ $ticket->creator->name }}</span>
                        <span aria-hidden="true">·</span>
                        <span>{{ $ticket->ageLabel() }}</span>
                    </div>

                    <div class="queue-card__actions">
                        <form method="POST" action="{{ route('tickets.approve', $ticket) }}">
                            @csrf
                            <x-button variant="confirm">
                                <x-icon name="check" class="btn__icon" />
                                موافقة
                            </x-button>
                        </form>

                        <x-button variant="reject" type="button" x-on:click="rejecting = ! rejecting"
                                  x-bind:aria-expanded="rejecting ? 'true' : 'false'">
                            رفض
                        </x-button>

                        <a class="btn btn--ghost queue-card__open" href="{{ route('tickets.show', $ticket) }}">
                            فتح التذكرة
                            <x-icon name="chevron-left" class="btn__icon" />
                        </a>
                    </div>

                    <form class="queue-card__reason" method="POST" action="{{ route('tickets.reject', $ticket) }}"
                          x-show="rejecting" x-cloak>
                        @csrf
                        <label class="u-label" for="reject-reason-{{ $ticket->id }}">سبب الرفض</label>
                        <textarea id="reject-reason-{{ $ticket->id }}" name="reason" class="textarea" rows="2"
                                  maxlength="500" placeholder="رفض من غير سبب مجرد حيطة…"></textarea>
                        <div class="queue-card__reason-actions">
                            <x-button variant="danger" size="sm">ارفضها</x-button>
                            <x-button variant="ghost" size="sm" type="button" x-on:click="rejecting = false">إلغاء</x-button>
                        </div>
                    </form>
                </article>
            @empty
                <p class="queue__empty">مفيش حاجة مستنية قرارك.</p>
            @endforelse
        </div>

        {{ $tickets->links() }}
    </div>
@endsection
