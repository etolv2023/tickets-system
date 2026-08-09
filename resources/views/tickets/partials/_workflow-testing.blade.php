{{-- F16: the tester's two buttons. Role-based (2026-07-24): shown to whoever
     holds an is_tester role on this ticket. --}}
@php
    $iAmTester = $ticket->roleAssignments->contains(fn ($a) => $a->user_id === $me->id && $a->role?->is_tester);
@endphp
@if ($iAmTester && in_array($ticket->status->value, ['dev_done', 'testing'], true))
    <x-collapsible-card title="التيست" name="testing" :open="true">
        <div class="stack stack--tight">
            <form method="POST" action="{{ route('tickets.verify', $ticket) }}">
                @csrf
                <x-button variant="primary" size="sm" block>تم التأكيد — اتحلت</x-button>
            </form>

            <form method="POST" action="{{ route('tickets.reopen', $ticket) }}" class="stack stack--tight">
                @csrf
                <input type="text" name="reason" class="input" placeholder="سبب الارتجاع (إجباري)" required>
                <x-button variant="ghost" size="sm" block>مرتجعة</x-button>
            </form>
        </div>
    </x-collapsible-card>
@endif
