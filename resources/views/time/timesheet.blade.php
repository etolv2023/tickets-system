@extends('layouts.app')

@section('title', 'ورقة وقتي')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">ورقة وقتي</h1>
                <p class="page-subtitle">
                    {{ $from->translatedFormat('j M') }} — {{ $to->translatedFormat('j M Y') }}
                </p>
            </div>
            <div class="page__actions">
                <span class="timesheet__total">{{ rtrim(rtrim(number_format($total, 2), '0'), '.') }} ساعة</span>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <div class="timesheet__nav">
            <x-button variant="ghost" :href="route('time.timesheet', ['week' => $from->subWeek()->toDateString()])">
                ← الأسبوع الي فات
            </x-button>
            <x-button variant="ghost" :href="route('time.timesheet')">الأسبوع ده</x-button>
            <x-button variant="ghost" :href="route('time.timesheet', ['week' => $from->addWeek()->toDateString()])">
                الأسبوع الجاي →
            </x-button>
        </div>

        <div class="timesheet__week">
            @foreach ($days as $day)
                @php
                    $hours = $byDay[$day->toDateString()] ?? 0;
                    // Capacity colouring: at, near, over. F14
                    $state = match (true) {
                        $hours == 0 => 'empty',
                        $hours > $capacity => 'over',
                        $hours >= $capacity * 0.85 => 'near',
                        default => 'at',
                    };
                @endphp

                <div class="timesheet__day timesheet__day--{{ $state }}">
                    <span class="timesheet__day-name">{{ $day->translatedFormat('l') }}</span>
                    <span class="timesheet__day-date">{{ $day->translatedFormat('j/n') }}</span>
                    <span class="timesheet__day-hours">
                        {{ $hours > 0 ? rtrim(rtrim(number_format($hours, 2), '0'), '.') : '—' }}
                    </span>
                </div>
            @endforeach
        </div>

        <p class="field__hint">
            سعتك اليومية {{ rtrim(rtrim($capacity, '0'), '.') }} ساعة. الأحمر معناه اليوم عدّى السعة.
        </p>

        <x-card title="التسجيلات" flush>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>التذكرة</th>
                            <th>الصب تاسك</th>
                            <th>ملاحظة</th>
                            <th class="table__cell--num">ساعات</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="u-nums">{{ $entry->spent_on->translatedFormat('D j M') }}</td>
                                <td>
                                    <a href="{{ route('tickets.show', $entry->ticket_id) }}">
                                        <span class="u-mono u-ltr">{{ $entry->ticket->ticket_number }}</span>
                                        {{ Str::limit($entry->ticket->title, 40) }}
                                    </a>
                                </td>
                                <td>{{ $entry->subtask?->title ?? '—' }}</td>
                                <td class="u-subtle">{{ $entry->note ?? '—' }}</td>
                                <td class="table__cell--num">{{ rtrim(rtrim($entry->hours, '0'), '.') }}</td>
                                <td class="table__cell--actions">
                                    <form method="POST" action="{{ route('time.destroy', $entry) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="ghost" size="sm">حذف</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="6">مسجّلتش وقت في الأسبوع ده.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
