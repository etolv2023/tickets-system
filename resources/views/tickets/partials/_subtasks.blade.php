<x-card :title="__('المهام الفرعية')" flush>
    <x-slot:actions>
        @if ($ticket->subtasks_total > 0)
            <div class="progress">
                {{-- The one inline style the rules allow: a single dynamic value
                     injected as a custom property (CLAUDE.md § 3). --}}
                <div class="progress__track">
                    <div class="progress__bar" style="--value: {{ $ticket->progressPercent() }}%"></div>
                </div>
                <span class="progress__label"><span data-subtask-counter>{{ $ticket->subtasks_done }}/{{ $ticket->subtasks_total }}</span> {{ __('مكتملة') }}</span>
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
                {{ __('لا توجد مهام فرعية. المهام الفرعية اختيارية — أنشئها إذا كان العمل يستحق التقسيم.') }}
            </p>
        @endforelse
    </div>

    <p class="subtasks__note">
        <x-icon name="star" size="0.9em" />
        <span>
            <strong>{{ __('لكل مهمة فرعية نقاطها الخاصة.') }}</strong>
            {{ __('تبدأ افتراضيًا بنقطة واحدة عند الإنشاء، ويمكن تعديلها لكل مهمة فرعية على حدة. عند حل التذكرة، كل مهمة فرعية') }} <strong>{{ __('مكتملة') }}</strong>
            {{ __('يحصل صاحبها على نقاطها بالضبط — دون تقسيم، ودون نقاط على مستوى التذكرة نفسها.') }}
        </span>
    </p>

    @can('create', [App\Models\TicketSubtask::class, $ticket])
        <div class="card__footer">
            @include('tickets.partials._subtask-form')
        </div>
    @endcan
</x-card>
