@extends('layouts.app')

@section('title', __('فروع التذاكر'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('فروع التذاكر') }}</h1>
                <p class="page-subtitle">
                    {{ __('أي التذاكر لها كود وأيها بلا كود — تتم المطابقة بالفرع الذي يبدأ اسمه برقم التذكرة.') }}
                    <br>
                    {{ __('الافتراضي هو') }} <strong>{{ __('المغلقة بلا فرع') }}</strong>{{ __('؛ غيّر أول عاملَي تصفية إذا أردت عكس السؤال.') }}
                    <br>
                    {{ __('العمود') }} <strong>{{ __('«الفروع حسب الجهة»') }}</strong> {{ __('يقارن الجهات المُسندة إلى التذكرة بالفروع الموجودة فعلًا — التذكرة المُسندة لمطوّري الأنظمة الخلفية والواجهات الأمامية يجب أن يكون لها فرع في كليهما، وإذا نقص أحدهما فسيظهر ذلك') }} <strong>{{ __('أحمر') }}</strong> {{ __('حتى لو كانت التذكرة «لها فرع».') }}
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
                            {{ __('مزامنة الآن') }}
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
                {{ __('تكامل GitHub معطّل، لذا تعتمد هذه القائمة على آخر مزامنة ناجحة. اضبط') }} <span class="u-mono u-ltr">GITHUB_ENABLED=true</span> {{ __('و') }}
                <span class="u-mono u-ltr">GITHUB_TOKEN</span> {{ __('ثم شغّل') }}
                <span class="u-mono u-ltr">php artisan github:check</span>.
            </x-alert>
        @endunless

        @include('github.partials._filters')

        <x-card flush>
            @if ($tickets->isEmpty())
                <div class="blank">
                    <p class="blank__title">{{ __('لا توجد تذاكر مطابقة لعوامل التصفية.') }}</p>
                    <p class="blank__body">{{ __('جرّب تغيير عامل تصفية الفرع أو الحالة.') }}</p>
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
                                <th>{{ __('الفروع حسب الجهة') }}</th>
                                <th>{{ __('محلولة') }}</th>
                                <th>{{ __('من يعمل عليها') }}</th>
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
                · {{ __('تعمل المزامنة التلقائية يوميًا الساعة 3 صباحًا.') }}
            @else
                {{ __('لم تتم أي مزامنة بعد.') }}
            @endif
        </span>

        {{ $tickets->links() }}
    </div>
@endsection
