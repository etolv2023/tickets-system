@extends('layouts.app')

@section('title', __('جلسة دخول بعين الغير'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">
                    {{ __(':actor بعين :target', ['actor' => $session->impersonator->name, 'target' => $session->impersonated->name]) }}
                </h1>
                <p class="page-subtitle">
                    {{ __('بدأت') }}
                    {{ $session->started_at->timezone(config('app.display_timezone'))->translatedFormat('j M Y — H:i') }}
                    @if ($session->isOpen())
                        · <strong>{{ __('لسه مفتوحة') }}</strong>
                    @else
                        · {{ __('خلصت') }}
                        {{ $session->ended_at->timezone(config('app.display_timezone'))->translatedFormat('H:i') }}
                        ({{ $session->durationLabel() }})
                    @endif
                    @if ($session->ip)
                        · <span class="u-mono u-ltr">{{ $session->ip }}</span>
                    @endif
                </p>
            </div>

            <div class="page__actions">
                <x-button variant="ghost" :href="route('admin.impersonations.index')">{{ __('رجوع للسجل') }}</x-button>
            </div>
        </div>

        <div class="note">
            {!! __('كل فعل تحت اتسجل في سجل التدقيق <strong>باسم :target</strong> — وده مقصود، عشان باقي الشاشات تفضل متفقة على مين عمل إيه. اللي عمله فعلياً هو <strong>:actor</strong>.', ['target' => e($session->impersonated->name), 'actor' => e($session->impersonator->name)]) !!}
        </div>

        <x-card flush>
            @if ($actions->isEmpty())
                <div class="blank">
                    <p class="blank__title">{{ __('مفيش أفعال في الجلسة دي.') }}</p>
                    <p class="blank__body">
                        {{ __('دخل وبص وخرج — مفيش حاجة اتغيّرت. (القراءة مش بتتسجل، السجل بيمسك التغييرات بس.)') }}
                    </p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('الوقت') }}</th>
                                <th>{{ __('الفعل') }}</th>
                                <th>{{ __('الكائن') }}</th>
                                <th>{{ __('التغيير') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($actions as $action)
                                <tr>
                                    <td class="u-mono">
                                        {{ $action->created_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i:s') }}
                                    </td>
                                    <td class="u-mono u-ltr">{{ $action->action }}</td>
                                    <td class="u-subtle u-mono u-ltr">
                                        {{ $action->subject_type ? class_basename($action->subject_type) : '—' }}
                                        @if ($action->subject_id)
                                            #{{ $action->subject_id }}
                                        @endif
                                    </td>
                                    <td>
                                        @if ($action->changes)
                                            <details class="cron-output">
                                                <summary class="cron-output__summary">{{ __('شوف') }}</summary>
                                                <pre class="cron-output__body u-ltr">{{ json_encode($action->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </details>
                                        @else
                                            <span class="u-subtle">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>

        {{ $actions->links() }}
    </div>
@endsection
