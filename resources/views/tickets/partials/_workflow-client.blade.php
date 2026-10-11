{{-- F06: telling the customer, then closing. The order is enforced. --}}
@canany(['notifyClient', 'close'])
    @if (in_array($ticket->status->value, \App\Models\TicketStatusDefinition::resolvedKeys(), true))
        {{-- name= is not optional here: the fold key is md5(title), and this shared
             one with the facts card that used to be called "العميل" too, so
             folding either folded both. --}}
        <x-collapsible-card :title="__('العميل')" name="client-actions" :open="true">
            <div class="stack stack--tight">
                @if ($ticket->client_notified_at)
                    <p class="field__hint">
                        {{ __('تم تسجيل إبلاغ العميل :when.', ['when' => $ticket->client_notified_at->diffForHumans()]) }}
                    </p>
                @else
                    @can('notifyClient', $ticket)
                        <form method="POST" action="{{ route('tickets.notify', $ticket) }}">
                            @csrf
                            <x-button variant="secondary" size="sm" block>{{ __('تسجيل إبلاغ العميل') }}</x-button>
                        </form>
                    @endcan
                @endif

                @can('close', $ticket)
                    @if ($ticket->status->value !== 'closed')
                        <form method="POST" action="{{ route('tickets.close', $ticket) }}">
                            @csrf
                            <x-button variant="primary" size="sm" block
                                      :disabled="$ticket->client_notified_at === null">
                                {{ __('إغلاق التذكرة نهائيًا') }}
                            </x-button>
                        </form>

                        @unless ($ticket->client_notified_at)
                            <p class="field__hint">{{ __('يلزم تسجيل إبلاغ العميل قبل الإغلاق.') }}</p>
                        @endunless
                    @endif
                @endcan
            </div>
        </x-collapsible-card>
    @endif
@endcanany
