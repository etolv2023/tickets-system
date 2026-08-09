{{-- F15: nothing happens on a feature until the admin decides. --}}
@can('approve', $ticket)
    {{-- Open by default: an approval waiting on you is the reason the ticket
         is in your queue at all. --}}
    <x-collapsible-card title="مستنية قرارك" name="approval" :open="true">
        <div class="stack stack--tight">
            <p class="field__hint">
                دي {{ $ticket->type->label() }} — متتوزعش قبل ما توافق.
            </p>

            <div class="row">
                <form method="POST" action="{{ route('tickets.approve', $ticket) }}">
                    @csrf
                    <x-button variant="primary" size="sm">موافقة</x-button>
                </form>

                <form method="POST" action="{{ route('tickets.reject', $ticket) }}" class="row">
                    @csrf
                    <input type="text" name="reason" class="input" placeholder="سبب الرفض (إجباري)" required>
                    <x-button variant="ghost" size="sm">رفض</x-button>
                </form>
            </div>
        </div>
    </x-collapsible-card>
@endcan
