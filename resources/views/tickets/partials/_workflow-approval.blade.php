{{-- F15: nothing happens on a feature until the admin decides. --}}
@can('approve', $ticket)
    {{-- Open by default: an approval waiting on you is the reason the ticket
         is in your queue at all. --}}
    <x-collapsible-card :title="__('قرار الاعتماد مطلوب')" name="approval" :open="true">
        <div class="stack stack--tight">
            <p class="field__hint">
                {{ __('هذه التذكرة من نوع :type، ولن يبدأ توزيع العمل قبل اعتمادها.', ['type' => $ticket->type->label()]) }}
            </p>

            <div class="row">
                <form method="POST" action="{{ route('tickets.approve', $ticket) }}">
                    @csrf
                    <x-button variant="primary" size="sm">{{ __('اعتماد وبدء التوزيع') }}</x-button>
                </form>

                <form method="POST" action="{{ route('tickets.reject', $ticket) }}" class="row">
                    @csrf
                    <input type="text" name="reason" class="input" placeholder="{{ __('سبب الرفض (مطلوب)') }}" required>
                    <x-button variant="ghost" size="sm">{{ __('رفض الطلب') }}</x-button>
                </form>
            </div>
        </div>
    </x-collapsible-card>
@endcan
