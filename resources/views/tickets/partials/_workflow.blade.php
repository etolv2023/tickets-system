@php
    $me = auth()->user();
    $myLogs = $ticket->workLogs->where('user_id', $me->id);
@endphp

{{-- F15: nothing happens on a feature until the admin decides. --}}
@can('approve', $ticket)
    <x-card title="مستنية قرارك">
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
    </x-card>
@endcan

{{-- F07: the start / finish buttons, one per side this user owns. --}}
@if ($myLogs->isNotEmpty())
    <x-card title="شغلي">
        <div class="stack stack--tight">
            @foreach ($myLogs as $log)
                <div class="row row--between">
                    <span>
                        {{ $log->side->label() }}
                        <x-badge :variant="$log->statusVariant()">{{ $log->statusLabel() }}</x-badge>
                    </span>

                    @can('manageWorkLog', $ticket)
                        @if ($log->status === 'pending')
                            <form method="POST" action="{{ route('tickets.work.start', [$ticket, $log]) }}">
                                @csrf
                                <x-button variant="primary" size="sm">بدأت</x-button>
                            </form>
                        @elseif ($log->status === 'in_progress')
                            <form method="POST" action="{{ route('tickets.work.finish', [$ticket, $log]) }}">
                                @csrf
                                <x-button variant="secondary" size="sm">خلصت</x-button>
                            </form>
                        @else
                            <span class="u-subtle">
                                {{ $log->finished_at?->diffForHumans() }}
                            </span>
                        @endif
                    @endcan
                </div>
            @endforeach
        </div>
    </x-card>
@endif

{{-- F16: the tester's two buttons. --}}
@if ($ticket->tester_id === $me->id && in_array($ticket->status->value, ['dev_done', 'testing'], true))
    <x-card title="التيست">
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
    </x-card>
@endif

{{-- F06: telling the customer, then closing. The order is enforced. --}}
@canany(['notifyClient', 'close'])
    @if (in_array($ticket->status->value, ['resolved', 'closed'], true))
        <x-card title="العميل">
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
        </x-card>
    @endif
@endcanany
