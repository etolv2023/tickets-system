@extends('layouts.app')

@section('title', 'اتقفلت من غير برانش')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">اتقفلت من غير برانش</h1>
                <p class="page-subtitle">
                    تذاكر اتعلّمت محلولة أو مقفولة، ومفيش ولا برانش في أي ريبو اسمه بيبدأ برقمها.
                    القايمة دي بتتحدّث كل ليلة الساعة 3 صباحاً من جيت هب.
                </p>
            </div>

            {{-- The denominator matters more than the count: "31" alone says
                 nothing, "31 من 402" says whether this is a habit or a slip. --}}
            <div class="gh-score">
                <span class="gh-score__value u-mono">{{ number_format($missingCount) }}</span>
                <span class="gh-score__label">من {{ number_format($settledCount) }} تذكرة متقفلة</span>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('github.missing') }}" class="filters">
            <div class="filters__bar">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                       placeholder="ابحث بالعنوان، أو الصق رقم تذكرة" class="input filters__search">

                <div class="filters__group">
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input" aria-label="اتحلت من">
                    <span class="u-subtle">→</span>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input" aria-label="اتحلت لـ">
                </div>

                <x-button variant="secondary">فلترة</x-button>

                @if (array_filter($filters))
                    <x-button variant="ghost" :href="route('github.missing')">مسح</x-button>
                @endif
            </div>
        </form>

        <x-card flush>
            @if ($tickets->isEmpty())
                <div class="blank">
                    <p class="blank__title">مفيش ولا تذكرة من غير برانش.</p>
                    <p class="blank__body">
                        كل تذكرة اتقفلت في المدى ده ليها برانش على جيت هب.
                    </p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>التذكرة</th>
                                <th>الشركة</th>
                                <th>الحالة</th>
                                <th>اتحلت</th>
                                <th>مين شغّال عليها</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td>
                                        <a href="{{ route('tickets.show', $ticket) }}" class="gh-miss__ticket">
                                            <span class="u-mono u-ltr">{{ $ticket->ticket_number }}</span>
                                            <span class="gh-miss__title">{{ Str::limit($ticket->title, 55) }}</span>
                                        </a>
                                    </td>
                                    <td>{{ $ticket->company?->name ?? 'داخلية' }}</td>
                                    <td>
                                        <x-badge :variant="$ticket->status->variant()">{{ $ticket->status->label() }}</x-badge>
                                    </td>
                                    <td class="u-mono">
                                        {{ $ticket->resolved_at?->timezone(config('app.display_timezone'))->translatedFormat('j M Y') ?? '—' }}
                                    </td>
                                    <td>
                                        <div class="gh-miss__people">
                                            @foreach ($ticket->roleAssignments as $assignment)
                                                @if ($assignment->user)
                                                    <x-avatar :user="$assignment->user" size="sm" />
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{ $tickets->links() }}
    </div>
@endsection
