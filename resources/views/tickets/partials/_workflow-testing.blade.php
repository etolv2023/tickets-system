{{-- F16: the tester's two buttons. --}}
@if ($ticket->tester_id === $me->id && in_array($ticket->status->value, ['dev_done', 'testing'], true))
    <x-collapsible-card title="التيست">
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
