@php
    $me = auth()->user();
    $myLogs = $ticket->workLogs->where('user_id', $me->id);
@endphp

{{-- Split into one file per action block (CLAUDE.md § 3 — this partial passed
     150 lines). Each @include shares this file's variable scope, same as a
     single long file would — nothing about what renders or when changed. --}}
@include('tickets.partials._workflow-approval')
@include('tickets.partials._workflow-my-work')
@include('tickets.partials._workflow-status-change')
@include('tickets.partials._workflow-testing')
@include('tickets.partials._workflow-client')
