{{-- F07: the start / finish buttons, one per side this user owns. --}}
@if ($myLogs->isNotEmpty())
    {{-- Open by default: this card only renders when there IS work of yours on
         this ticket, and it is the بدأت / خلصت buttons. --}}
    <x-collapsible-card title="شغلي" name="my-work" :open="true">
        <div class="stack stack--tight">
            @foreach ($myLogs as $log)
                <div class="row row--between">
                    <span>
                        {{ $log->roleLabel() }}
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
    </x-collapsible-card>
@endif
