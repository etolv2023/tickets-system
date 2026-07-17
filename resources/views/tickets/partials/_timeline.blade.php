@php
    /*
     * F05: the timeline merges comments and status changes chronologically —
     * one stream, not two lists. Subtasks and time entries join it in phase 4.
     * Both sources are already eager-loaded by the controller.
     */
    $entries = $comments
        ->map(fn ($c) => ['kind' => 'comment', 'at' => $c->created_at, 'model' => $c])
        ->concat($ticket->statusHistory->map(fn ($h) => ['kind' => 'event', 'at' => $h->created_at, 'model' => $h]))
        ->sortBy('at')
        ->values();
@endphp

<x-card title="الخط الزمني">
    <x-slot:actions>
        <span class="u-subtle">{{ $comments->count() }} تعليق · {{ $ticket->statusHistory->count() }} حدث</span>
    </x-slot:actions>

    <div class="stack">
        @if ($entries->isEmpty())
            <p class="prose__empty">مفيش حاجة حصلت لسه.</p>
        @else
            <div class="timeline">
                @foreach ($entries as $entry)
                    @if ($entry['kind'] === 'comment')
                        @include('tickets.partials._timeline-comment', ['comment' => $entry['model']])
                    @else
                        @include('tickets.partials._timeline-event', ['event' => $entry['model']])
                    @endif
                @endforeach
            </div>
        @endif

        @can('comment', $ticket)
            <hr class="divider">
            @include('tickets.partials._comment-form')
        @endcan
    </div>
</x-card>
