@extends('layouts.install')

@section('title', 'فحص المتطلبات')

@section('content')
    <div class="form-stack">
        @unless ($result['passed'])
            <x-alert variant="error">
                فيه متطلبات ناقصة. صلّحها في السيرفر وبعدين حدّث الصفحة.
            </x-alert>
        @endunless

        <div class="checklist">
            @foreach ($result['checks'] as $check)
                <div class="checklist__row">
                    <svg @class(['checklist__mark', 'checklist__mark--ok' => $check['ok'], 'checklist__mark--fail' => ! $check['ok']])
                         viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        @if ($check['ok'])
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd" />
                        @else
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.7 7.3a1 1 0 00-1.4 1.4L8.6 10l-1.3 1.3a1 1 0 101.4 1.4L10 11.4l1.3 1.3a1 1 0 001.4-1.4L11.4 10l1.3-1.3a1 1 0 10-1.4-1.4L10 8.6 8.7 7.3z" clip-rule="evenodd" />
                        @endif
                    </svg>

                    <span class="checklist__name">
                        {{ $check['name'] }}
                        <span class="checklist__hint">{{ $check['hint'] }}</span>
                    </span>

                    <span class="checklist__value">{{ $check['value'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="install__nav">
            <x-button variant="ghost" :href="route('install.requirements')">إعادة الفحص</x-button>

            @if ($result['passed'])
                <x-button variant="primary" :href="route('install.database')">التالي — قاعدة البيانات</x-button>
            @else
                <x-button variant="primary" disabled aria-disabled="true">التالي — قاعدة البيانات</x-button>
            @endif
        </div>
    </div>
@endsection
