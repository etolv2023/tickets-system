@php
    $estimate = (float) ($ticket->original_estimate_hours ?? 0);
    $spent = (float) $ticket->spent_hours;
    // Capped at 100% so the bar can't overflow; the colour carries the overrun.
    $percent = $estimate > 0 ? min(100, (int) round($spent / $estimate * 100)) : 0;
    $remaining = max(0, $estimate - $spent);
@endphp

<x-card title="الوقت">
    <div class="stack stack--tight">
        @if ($estimate > 0)
            {{-- F09: green inside the estimate, amber over it, red past double. --}}
            <div class="estimate estimate--{{ $ticket->estimateVariant() }}">
                <div class="estimate__row">
                    <span>المقدّر <strong>{{ rtrim(rtrim(number_format($estimate, 2), '0'), '.') }}</strong> س</span>
                    <span class="estimate__spent">الفعلي {{ rtrim(rtrim(number_format($spent, 2), '0'), '.') }} س</span>
                </div>

                <div class="estimate__bar">
                    <div class="estimate__fill" style="--value: {{ $percent }}%"></div>
                </div>

                <div class="estimate__row">
                    <span class="u-subtle">المتبقي {{ rtrim(rtrim(number_format($remaining, 2), '0'), '.') }} س</span>
                    @if ($spent > $estimate)
                        <span class="u-subtle">عدّى التقدير بـ {{ rtrim(rtrim(number_format($spent - $estimate, 2), '0'), '.') }} س</span>
                    @endif
                </div>
            </div>
        @else
            <p class="field__hint">
                مفيش تقدير. ضيف صب تاسكس بتقديرات وهيتجمعوا هنا أوتوماتيك.
                @if ($spent > 0)
                    <br>المسجّل لحد دلوقتي: <strong>{{ rtrim(rtrim(number_format($spent, 2), '0'), '.') }}</strong> س.
                @endif
            </p>
        @endif

        @can('time.log')
            <hr class="divider">

            <form method="POST" action="{{ route('tickets.time.store', $ticket) }}" class="stack stack--tight">
                @csrf

                <div class="form-grid">
                    <x-field name="hours" label="ساعات" type="number" step="0.25" min="0.25" max="24"
                             required placeholder="1.5" />
                    <x-field name="spent_on" label="التاريخ" type="date"
                             :value="now(config('app.display_timezone'))->toDateString()"
                             required max="{{ now(config('app.display_timezone'))->toDateString() }}" />
                </div>

                @if ($ticket->subtasks->isNotEmpty())
                    <x-field name="subtask_id" label="على أنهي صب تاسك؟">
                        <select id="subtask_id" name="subtask_id" class="select">
                            <option value="">التذكرة نفسها</option>
                            @foreach ($ticket->subtasks as $subtask)
                                <option value="{{ $subtask->id }}">{{ $subtask->title }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif

                <x-field name="note" label="ملاحظة" placeholder="اختياري" />

                <x-button variant="secondary" size="sm" block>سجّل الوقت</x-button>
            </form>

            @if ($myTimeEntries->isNotEmpty())
                <hr class="divider">
                <p class="u-subtle">وقتي على التذكرة دي</p>

                <div class="facts">
                    @foreach ($myTimeEntries as $entry)
                        @php
                            // Resolved from the subtasks already loaded on the
                            // ticket — an eager load here would be a second
                            // query for rows we hold.
                            $entrySubtask = $entry->subtask_id
                                ? $ticket->subtasks->firstWhere('id', $entry->subtask_id)
                                : null;
                        @endphp
                        <div class="facts__row">
                            <span class="facts__label">
                                {{ $entry->spent_on->translatedFormat('j M') }}
                                @if ($entrySubtask)
                                    — {{ Str::limit($entrySubtask->title, 20) }}
                                @endif
                            </span>
                            <span class="row">
                                <span class="facts__value facts__value--num">
                                    {{ rtrim(rtrim($entry->hours, '0'), '.') }} س
                                </span>
                                <form method="POST" action="{{ route('time.destroy', $entry) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="ghost" size="sm">×</x-button>
                                </form>
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        @endcan
    </div>
</x-card>
