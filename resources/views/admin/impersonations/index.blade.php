@extends('layouts.app')

@section('title', __('سجل العمل نيابةً عن المستخدمين'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('سجل العمل نيابةً عن المستخدمين') }}</h1>
                <p class="page-subtitle">
                    {{ __('من عمل نيابةً عن من، ومتى، وماذا فعل.') }}
                    <br>
                    {{ __('تُسجَّل الإجراءات نفسها') }} <strong>{{ __('باسم صاحب الحساب') }}</strong> {{ __('— لتبقى كل شاشة صادقة بشأن من أنجز العمل. هذه الصفحة هي المكان الوحيد الذي يعرف من كان يستخدم لوحة المفاتيح.') }}
                </p>
            </div>
        </div>

        <x-card flush>
            @if ($sessions->isEmpty())
                <div class="blank">
                    <p class="blank__title">{{ __('لم يعمل أحد نيابةً عن أحد.') }}</p>
                    <p class="blank__body">{{ __('تمتلئ هذه الصفحة تلقائيًا عند أول استخدام لهذه الصلاحية.') }}</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>{{ __('المستخدم') }}</th>
                                <th>{{ __('عمل نيابةً عن') }}</th>
                                <th>{{ __('البداية') }}</th>
                                <th>{{ __('المدة') }}</th>
                                <th class="table__cell--num">{{ __('أفعال') }}</th>
                                <th>IP</th>
                                <th class="table__cell--actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sessions as $session)
                                <tr>
                                    <td>
                                        <div class="row">
                                            <x-avatar :user="$session->impersonator" size="sm" />
                                            {{ $session->impersonator->name }}
                                        </div>
                                    </td>

                                    <td>
                                        <div class="row">
                                            <x-avatar :user="$session->impersonated" size="sm" />
                                            {{ $session->impersonated->name }}
                                        </div>
                                    </td>

                                    <td class="u-mono">
                                        {{ $session->started_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}
                                    </td>

                                    <td>
                                        @if ($session->isOpen())
                                            <x-badge variant="amber">{{ __('ما زالت مفتوحة') }}</x-badge>
                                        @else
                                            <span class="u-subtle">{{ $session->durationLabel() }}</span>
                                        @endif
                                    </td>

                                    {{-- The number that says whether this was a look or a visit. --}}
                                    <td class="table__cell--num u-mono">
                                        @if ($session->actions_count > 0)
                                            <strong>{{ $session->actions_count }}</strong>
                                        @else
                                            <span class="u-subtle">—</span>
                                        @endif
                                    </td>

                                    <td class="u-mono u-ltr table__cell--muted">{{ $session->ip ?? '—' }}</td>

                                    <td class="table__cell--actions">
                                        <x-button variant="ghost" size="sm"
                                                  :href="route('admin.impersonations.show', $session)">
                                            {{ __('ما الذي فعله') }}
                                        </x-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{ $sessions->links() }}
    </div>
@endsection
