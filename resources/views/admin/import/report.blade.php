@extends('layouts.app')

@section('title', __('تقرير الاستيراد'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('تقرير الاستيراد') }}</h1>
                <p class="page-subtitle">
                    {{ $batch->original_filename }} · {{ $batch->type->label() }} ·
                    {{ $batch->created_at?->timezone(config('app.display_timezone'))->translatedFormat('j F Y — H:i') }}
                </p>
            </div>
            <div class="page__actions">
                <x-badge :variant="$batch->statusVariant()">{{ $batch->statusLabel() }}</x-badge>
            </div>
        </div>

        @if (session('status'))
            <x-alert>{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        @if ($batch->status === 'importing')
            <x-alert>
                {{ __('الاستيراد شغال في الخلفية دلوقتي. حدّث الصفحة عشان تشوف النتيجة.') }}
            </x-alert>
        @endif

        <div class="import-summary">
            <div class="import-summary__item import-summary__item--create">
                <div class="import-summary__value">{{ $batch->created_rows }}</div>
                <div class="import-summary__label">{{ __('اتعمل جديد') }}</div>
            </div>
            <div class="import-summary__item import-summary__item--update">
                <div class="import-summary__value">{{ $batch->updated_rows }}</div>
                <div class="import-summary__label">{{ __('اتحدّث') }}</div>
            </div>
            <div class="import-summary__item import-summary__item--fail">
                <div class="import-summary__value">{{ $batch->failed_rows }}</div>
                <div class="import-summary__label">{{ __('فشل') }}</div>
            </div>
            <div class="import-summary__item">
                <div class="import-summary__value">{{ $batch->total_rows }}</div>
                <div class="import-summary__label">{{ __('إجمالي الصفوف') }}</div>
            </div>
        </div>

        @if (filled($batch->errors))
            <x-card :title="__('الصفوف الي فشلت')" flush>
                <x-slot:actions>
                    <x-button variant="secondary" size="sm" :href="route('admin.import.errors', $batch)">
                        {{ __('نزّل ملف الأخطاء') }}
                    </x-button>
                </x-slot:actions>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('الصف') }}</th>
                                <th>{{ __('العمود') }}</th>
                                <th>{{ __('السبب') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (array_slice($batch->errors, 0, 100) as $error)
                                <tr>
                                    <td class="u-nums">{{ $error['row'] }}</td>
                                    <td class="u-mono u-ltr">{{ $error['column'] }}</td>
                                    <td>{{ $error['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @elseif ($batch->status === 'completed')
            <x-alert variant="success">{{ __('كل الصفوف عدّت من غير أخطاء.') }}</x-alert>
        @endif

        <div class="form-actions">
            <x-button variant="ghost" :href="route('admin.import.index')">{{ __('رجوع للاستيراد') }}</x-button>

            @if ($batch->type->value === 'users')
                <x-button variant="secondary" :href="route('admin.users.index')">{{ __('شوف المستخدمين') }}</x-button>
            @else
                <x-button variant="secondary" :href="route('admin.companies.index')">{{ __('شوف الشركات') }}</x-button>
            @endif
        </div>
    </div>
@endsection
