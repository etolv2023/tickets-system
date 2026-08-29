@extends('layouts.app')

@section('title', 'سجل الدخول بعين الغير')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">سجل الدخول بعين الغير</h1>
                <p class="page-subtitle">
                    مين دخل بعين مين، امتى، وعمل إيه.
                    <br>
                    الأفعال نفسها بتتسجل <strong>باسم صاحب الحساب</strong> — عشان كل شاشة تفضل صادقة عن
                    مين خلّص الشغل. الصفحة دي هي المكان الوحيد اللي بيعرف مين كان قاعد على الكيبورد.
                </p>
            </div>
        </div>

        <x-card flush>
            @if ($sessions->isEmpty())
                <div class="blank">
                    <p class="blank__title">محدش دخل بعين حد.</p>
                    <p class="blank__body">الصفحة دي بتتملي لوحدها أول ما حد يستخدم الصلاحية دي.</p>
                </div>
            @else
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>مين</th>
                                <th>دخل بعين</th>
                                <th>بدأت</th>
                                <th>المدة</th>
                                <th class="table__cell--num">أفعال</th>
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
                                            <x-badge variant="amber">لسه مفتوحة</x-badge>
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
                                            عمل إيه
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
