@extends('layouts.app')

@section('title', 'تذاكر من غير برانش')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">تذاكر من غير برانش</h1>
                <p class="page-subtitle">
                    تذاكر مفيش ولا برانش في أي ريبو اسمه بيبدأ برقمها.
                    الافتراضي هو <strong>اللي اتقفلت</strong> — غيّر فلتر الحالة لو عايز تشوف الشغل المفتوح كمان.
                </p>
            </div>

            <div class="gh-head">
                {{-- The denominator matters more than the count: "13" alone says
                     nothing, "13 من 39" says whether this is a habit or a slip.
                     Both numbers follow the filters, so they always describe the
                     same rows you are looking at. --}}
                <div class="gh-score">
                    <span class="gh-score__value u-mono">{{ number_format($missingCount) }}</span>
                    <span class="gh-score__label">من {{ number_format($totalCount) }} تذكرة</span>
                </div>

                {{-- Its own form, outside the filter bar: that one is a GET and
                     HTML has no nested forms. --}}
                <form method="POST" action="{{ route('github.sync') }}">
                    @csrf
                    <x-button variant="secondary">
                        <x-icon name="refresh" class="btn__icon" />
                        زامن دلوقتي
                    </x-button>
                </form>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        @unless ($connected)
            <x-alert variant="error">
                تكامل جيت هب مقفول، فالقايمة دي مبنية على آخر مزامنة نجحت.
                حط <span class="u-mono u-ltr">GITHUB_ENABLED=true</span> و
                <span class="u-mono u-ltr">GITHUB_TOKEN</span> وشغّل
                <span class="u-mono u-ltr">php artisan github:check</span>.
            </x-alert>
        @endunless

        @include('github.partials._filters')

        <x-card flush>
            @if ($tickets->isEmpty())
                <div class="blank">
                    <p class="blank__title">مفيش ولا تذكرة من غير برانش.</p>
                    <p class="blank__body">كل تذكرة داخلة في الفلاتر دي ليها برانش على جيت هب.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>التذكرة</th>
                                <th>الشركة</th>
                                <th>النوع</th>
                                <th>الأولوية</th>
                                <th>الحالة</th>
                                <th>اتحلت</th>
                                <th>مين شغّال عليها</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                @include('github.partials._row', ['ticket' => $ticket])
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        <span class="tickets__count">
            @if ($lastSyncedAt)
                آخر مزامنة:
                {{ \Illuminate\Support\Carbon::parse($lastSyncedAt)->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}
                · المزامنة التلقائية كل ليلة 3 صباحاً.
            @else
                مفيش مزامنة اتعملت لسه.
            @endif
        </span>

        {{ $tickets->links() }}
    </div>
@endsection
