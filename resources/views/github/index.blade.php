@extends('layouts.app')

@section('title', __('برانشات التذاكر'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('برانشات التذاكر') }}</h1>
                <p class="page-subtitle">
                    {{ __('أنهي تذاكر ليها كود وراها وأنهي لأ — المطابقة بالبرانش اللي اسمه بيبدأ برقم التذكرة.') }}
                    <br>
                    {{ __('الافتراضي هو') }} <strong>{{ __('اللي اتقفلت وملهاش برانش') }}</strong>{{ __('؛ غيّر أول فلترين لو عايز تقلب السؤال.') }}
                    <br>
                    {{ __('عمود') }} <strong>{{ __('«البرانشات بالجهة»') }}</strong> {{ __('بيقارن الجهات المسنودة على التذكرة بالبرانشات الموجودة فعلاً — تذكرة عليها باك وفرونت لازم يكون ليها برانش في الاتنين، ولو واحد ناقص هيبان') }} <strong>{{ __('أحمر') }}</strong> {{ __('حتى لو التذكرة «ليها برانش».') }}
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
                        {{ $modeLabel }} · {{ __('من :count تذكرة', ['count' => number_format($totalCount)]) }}
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
                            {{ __('زامن دلوقتي') }}
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
                {{ __('تكامل جيت هب مقفول، فالقايمة دي مبنية على آخر مزامنة نجحت. حط') }} <span class="u-mono u-ltr">GITHUB_ENABLED=true</span> {{ __('و') }}
                <span class="u-mono u-ltr">GITHUB_TOKEN</span> {{ __('وشغّل') }}
                <span class="u-mono u-ltr">php artisan github:check</span>.
            </x-alert>
        @endunless

        @include('github.partials._filters')

        <x-card flush>
            @if ($tickets->isEmpty())
                <div class="blank">
                    <p class="blank__title">{{ __('مفيش تذاكر بالفلاتر دي.') }}</p>
                    <p class="blank__body">{{ __('جرّب تغيّر فلتر البرانش أو الحالة.') }}</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>{{ __('التذكرة') }}</th>
                                <th>{{ __('الشركة') }}</th>
                                <th>{{ __('النوع') }}</th>
                                <th>{{ __('الأولوية') }}</th>
                                <th>{{ __('الحالة') }}</th>
                                <th>{{ __('البرانشات بالجهة') }}</th>
                                <th>{{ __('اتحلت') }}</th>
                                <th>{{ __('مين شغّال عليها') }}</th>
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
                {{ __('آخر مزامنة:') }}
                {{ \Illuminate\Support\Carbon::parse($lastSyncedAt)->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}
                · {{ __('المزامنة التلقائية كل ليلة 3 صباحاً.') }}
            @else
                {{ __('مفيش مزامنة اتعملت لسه.') }}
            @endif
        </span>

        {{ $tickets->links() }}
    </div>
@endsection
