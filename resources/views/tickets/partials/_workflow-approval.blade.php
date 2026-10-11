{{-- F15: nothing happens on a feature until the admin decides. --}}
@can('approve', $ticket)
    {{-- Open by default: an approval waiting on you is the reason the ticket
         is in your queue at all. --}}
    <x-collapsible-card title="قرار الاعتماد مطلوب" name="approval" :open="true">
        <div class="stack stack--tight">
            <p class="field__hint">
                هذه التذكرة من نوع {{ $ticket->type->label() }}، ولن يبدأ توزيع العمل قبل اعتمادها.
            </p>

            <div class="row">
                <form method="POST" action="{{ route('tickets.approve', $ticket) }}">
                    @csrf
                    <x-button variant="primary" size="sm">اعتماد وبدء التوزيع</x-button>
                </form>

                <form method="POST" action="{{ route('tickets.reject', $ticket) }}" class="row">
                    @csrf
                    <input type="text" name="reason" class="input" placeholder="سبب الرفض (مطلوب)" required>
                    <x-button variant="ghost" size="sm">رفض الطلب</x-button>
                </form>
            </div>
        </div>
    </x-collapsible-card>
@endcan
