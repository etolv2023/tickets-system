<x-card title="الصب تاسكس" flush>
    <x-slot:actions>
        @if ($ticket->subtasks_total > 0)
            <div class="progress">
                {{-- The one inline style the rules allow: a single dynamic value
                     injected as a custom property (CLAUDE.md § 3). --}}
                <div class="progress__track">
                    <div class="progress__bar" style="--value: {{ $ticket->progressPercent() }}%"></div>
                </div>
                <span class="progress__label">{{ $ticket->subtasks_done }}/{{ $ticket->subtasks_total }} مكتملة</span>
            </div>
        @endif
    </x-slot:actions>

    @if (session('subtask_warning'))
        <div class="card__body">
            <x-alert variant="error">{{ session('subtask_warning') }}</x-alert>
        </div>
    @endif

    <div
        class="subtasks"
        data-sortable="subtasks"
        data-reorder-url="{{ route('tickets.subtasks.reorder', $ticket) }}"
    >
        @forelse ($ticket->subtasks as $subtask)
            @include('tickets.partials._subtask-row', ['subtask' => $subtask])
        @empty
            <p class="card__empty">
                مفيش صب تاسكس. الصب تاسكس اختيارية — اعملها لو الشغل يستاهل تقسيم.
            </p>
        @endforelse
    </div>

    @can('create', [App\Models\TicketSubtask::class, $ticket])
        <div class="card__footer">
            @include('tickets.partials._subtask-form')
        </div>
    @endcan
</x-card>
