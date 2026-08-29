@extends('layouts.app')

@section('title', 'برانشات التذاكر')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">برانشات التذاكر</h1>
                <p class="page-subtitle">
                    أنهي تذاكر ليها كود وراها وأنهي لأ — المطابقة بالبرانش اللي اسمه بيبدأ برقم التذكرة.
                    <br>
                    الافتراضي هو <strong>اللي اتقفلت وملهاش برانش</strong>؛ غيّر أول فلترين لو عايز تقلب السؤال.
                    <br>
                    عمود <strong>«البرانشات بالجهة»</strong> بيقارن الجهات المسنودة على التذكرة بالبرانشات
                    الموجودة فعلاً — تذكرة عليها باك وفرونت لازم يكون ليها برانش في الاتنين، ولو واحد ناقص
                    هيبان <strong>أحمر</strong> حتى لو التذكرة «ليها برانش».
                </p>
            </div>

            <div class="gh-head">
                {{-- The denominator matters more than the count: "13" alone says
                     nothing, "13 من 39" says whether this is a habit or a slip.
                     Both numbers follow the filters, so they always describe the
                     same rows you are looking at. --}}
                <div class="gh-score">
                    <span class="gh-score__value u-mono">{{ number_format($matchedCount) }}</span>
                    <span class="gh-score__label">
                        {{ $modeLabel }} · من {{ number_format($totalCount) }} تذكرة
                    </span>
                </div>

                {{-- Its own form, outside the filter bar: that one is a GET and
                     HTML has no nested forms.

                     ★ (2026-08-29) Stays on github.audit while the screen itself
                     is open to every developer: reading the four repositories is
                     four walks of every branch they hold, and a button that
                     queues that is not one for everybody to press. --}}
                @can('github.audit')
                    <form method="POST" action="{{ route('github.sync') }}">
                        @csrf
                        <x-button variant="secondary">
                            <x-icon name="refresh" class="btn__icon" />
                            زامن دلوقتي
                        </x-button>
                    </form>
                @endcan
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
                    <p class="blank__title">مفيش تذاكر بالفلاتر دي.</p>
                    <p class="blank__body">جرّب تغيّر فلتر البرانش أو الحالة.</p>
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
                                <th>البرانشات بالجهة</th>
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
