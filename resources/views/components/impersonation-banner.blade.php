{{-- ★ (2026-08-29) F29 — «انت داخل بعين فلان».

     Impersonation here is FULL: everything done while it runs is done as that
     person and lands under their name. So the single most dangerous thing that
     can happen is forgetting — writing a comment, moving a status, finishing a
     work log while believing you are yourself.

     Which is why this is a bar across the top of every page and not a badge in
     a corner. It is deliberately the loudest thing in the interface, it does not
     collapse, it cannot be dismissed, and the way out is inside it.

     Renders nothing at all when no session is running, so it costs an ordinary
     page one session lookup. --}}

@php
    $impersonating = session()->has(\App\Http\Controllers\Admin\ImpersonationController::SESSION_KEY);
@endphp

@if ($impersonating)
    <div class="impersonation" role="status">
        <x-icon name="user" class="impersonation__icon" />

        <span class="impersonation__text">
            انت شغّال دلوقتي بعين <strong>{{ auth()->user()->name }}</strong> —
            أي حاجة تعملها هتتسجل باسمه، وهتتربط بجلسة الدخول دي في السجل.
        </span>

        <form method="POST" action="{{ route('admin.impersonate.stop') }}">
            @csrf
            @method('DELETE')
            <x-button variant="secondary" size="sm">ارجع لحسابك</x-button>
        </form>
    </div>
@endif
