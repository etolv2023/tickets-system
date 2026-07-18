@extends('layouts.portal')

@section('title', $ticket->ticket_number)

@section('content')
    @php
        // The heading needs the newest public reply and the thread reads best
        // oldest-first; both come off the one loaded collection, no extra query.
        $lastReply = $comments->last();
    @endphp

    <div class="portal__ticket-head">
        <div class="portal__ticket-meta">
            <span class="portal__ticket-ref u-mono u-ltr">{{ $ticket->ticket_number }}</span>
            @if ($ticket->company)
                <x-badge variant="neutral">{{ $ticket->company->name }}</x-badge>
            @endif
        </div>
        <h1 class="portal__ticket-title">{{ $ticket->title }}</h1>
    </div>

    @include('portal.partials._facts')

    @if (session('status'))
        <x-alert variant="success">{{ session('status') }}</x-alert>
    @endif

    <x-form-errors />

    @if ($ticket->description)
        <x-card title="اللي اتبلّغ عنه">
            <div class="prose">{!! $ticket->description !!}</div>
        </x-card>
    @endif

    {{-- The reply box lives in the thread's footer, not in a card of its own.
         Writing a reply is the last step of reading the conversation — a
         separate floating form said otherwise, and left a bare textarea and a
         native "Choose Files" button sitting in the middle of the page. --}}
    <x-card title="المحادثة" flush>
        @include('portal.partials._thread')

        <x-slot:footer>
            <form method="POST" action="{{ route('portal.comment', $ticket->ticket_number) }}"
                  class="portal-composer" enctype="multipart/form-data">
                @csrf

                <span class="u-label">اكتب ردك</span>

                {{-- The same editor the team writes in. Whatever it produces is
                     untrusted either way: TicketService runs Purifier over the
                     body on the server before it is stored (CLAUDE.md § 5), so
                     the toolbar decides what the client is offered, never what
                     the server accepts. --}}
                <x-editor name="body" simple placeholder="اكتب أي تفاصيل تساعد الفريق…" />

                <x-uploader name="files" :max="3" :accept="$uploadAccept" :hint="$uploadHint" />

                <div class="portal-composer__actions">
                    <x-button variant="primary">إرسال الرد</x-button>
                </div>
            </form>
        </x-slot:footer>
    </x-card>
@endsection
