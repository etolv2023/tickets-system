{{-- F06: telling the customer, then closing. The order is enforced. --}}
@canany(['notifyClient', 'close'])
    @if (in_array($ticket->status->value, ['resolved', 'closed'], true))
        {{-- name= is not optional here: the fold key is md5(title), and this shared
             one with the facts card that used to be called "العميل" too, so
             folding either folded both. --}}
        <x-collapsible-card title="العميل" name="client-actions" :open="true">
            <div class="stack stack--tight">
                @if ($ticket->client_notified_at)
                    <p class="field__hint">
                        اتبلغ {{ $ticket->client_notified_at->diffForHumans() }}.
                    </p>
                @else
                    @can('notifyClient', $ticket)
                        <form method="POST" action="{{ route('tickets.notify', $ticket) }}">
                            @csrf
                            <x-button variant="secondary" size="sm" block>تم إبلاغ العميل</x-button>
                        </form>
                    @endcan
                @endif

                @can('close', $ticket)
                    @if ($ticket->status->value !== 'closed')
                        <form method="POST" action="{{ route('tickets.close', $ticket) }}">
                            @csrf
                            <x-button variant="primary" size="sm" block
                                      :disabled="$ticket->client_notified_at === null">
                                اقفل التذكرة
                            </x-button>
                        </form>

                        @unless ($ticket->client_notified_at)
                            <p class="field__hint">لازم تبلغ العميل الأول.</p>
                        @endunless
                    @endif
                @endcan
            </div>
        </x-collapsible-card>
    @endif
@endcanany
