<x-card title="المشكلة">
    <x-slot:actions>
        <span class="u-subtle">
            فتحها {{ $ticket->creator->name }} ·
            {{ $ticket->reported_at->timezone(config('app.display_timezone'))->translatedFormat('j F Y — H:i') }}
        </span>
    </x-slot:actions>

    <div class="stack">
        @if ($ticket->description)
            {{-- Purifier ran over this before it was stored (F04.1), and the
                 whitelist has no script, iframe, style or on* handlers. This is
                 the one place raw output is correct — escaping it would show
                 the user their own markup as text. --}}
            <div class="prose">{!! $ticket->description !!}</div>
        @else
            <p class="prose__empty">مفيش وصف.</p>
        @endif

        @if ($ticket->bodyAttachments->isNotEmpty())
            <hr class="divider">
            <x-attachment-gallery :attachments="$ticket->bodyAttachments" />
        @endif
    </div>
</x-card>
