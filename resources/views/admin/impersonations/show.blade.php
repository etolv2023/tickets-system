@extends('layouts.app')

@section('title', 'جلسة دخول بعين الغير')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">
                    {{ $session->impersonator->name }} بعين {{ $session->impersonated->name }}
                </h1>
                <p class="page-subtitle">
                    بدأت
                    {{ $session->started_at->timezone(config('app.display_timezone'))->translatedFormat('j M Y — H:i') }}
                    @if ($session->isOpen())
                        · <strong>لسه مفتوحة</strong>
                    @else
                        · خلصت
                        {{ $session->ended_at->timezone(config('app.display_timezone'))->translatedFormat('H:i') }}
                        ({{ $session->durationLabel() }})
                    @endif
                    @if ($session->ip)
                        · <span class="u-mono u-ltr">{{ $session->ip }}</span>
                    @endif
                </p>
            </div>

            <div class="page__actions">
                <x-button variant="ghost" :href="route('admin.impersonations.index')">رجوع للسجل</x-button>
            </div>
        </div>

        <div class="note">
            كل فعل تحت اتسجل في سجل التدقيق <strong>باسم {{ $session->impersonated->name }}</strong> —
            وده مقصود، عشان باقي الشاشات تفضل متفقة على مين عمل إيه.
            اللي عمله فعلياً هو <strong>{{ $session->impersonator->name }}</strong>.
        </div>

        <x-card flush>
            @if ($actions->isEmpty())
                <div class="blank">
                    <p class="blank__title">مفيش أفعال في الجلسة دي.</p>
                    <p class="blank__body">
                        دخل وبص وخرج — مفيش حاجة اتغيّرت.
                        (القراءة مش بتتسجل، السجل بيمسك التغييرات بس.)
                    </p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>الوقت</th>
                                <th>الفعل</th>
                                <th>الكائن</th>
                                <th>التغيير</th>
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
                                                <summary class="cron-output__summary">شوف</summary>
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
