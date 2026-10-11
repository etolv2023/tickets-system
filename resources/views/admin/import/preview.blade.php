@extends('layouts.app')

@section('title', __('معاينة الاستيراد'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('معاينة الاستيراد') }}</h1>
                <p class="page-subtitle">
                    {{ $batch->original_filename }} · {{ $batch->type->label() }} · {{ trans_choice(':count صف|صف واحد|صفّان|:count صفوف|:count صفًا|:count صف', $batch->total_rows) }}
                </p>
            </div>
        </div>

        {{-- Nothing has been written yet. This is what the confirm button will do. --}}
        <x-alert>
            <strong>{{ __('لم يُكتب أي صف بعد.') }}</strong>
            {{ __('هذه الأرقام هي ما سيحدث بالضبط إذا أكدت.') }}
        </x-alert>

        <div class="import-summary">
            <div class="import-summary__item import-summary__item--create">
                <div class="import-summary__value">{{ $counts['create'] }}</div>
                <div class="import-summary__label">{{ __('سيُنشأ جديدًا') }}</div>
            </div>
            <div class="import-summary__item import-summary__item--update">
                <div class="import-summary__value">{{ $counts['update'] }}</div>
                <div class="import-summary__label">{{ __('سيُحدَّث (موجود مسبقًا)') }}</div>
            </div>
            <div class="import-summary__item import-summary__item--fail">
                <div class="import-summary__value">{{ $counts['fail'] }}</div>
                <div class="import-summary__label">{{ __('به خطأ — سيتم تخطّيه') }}</div>
            </div>
        </div>

        @if ($counts['fail'] > 0)
            <x-alert variant="error">
                {{ __('يوجد :count صف بها أخطاء. سيتم تخطّي هذه الصفوف وتكمل البقية بشكل طبيعي.', ['count' => $counts['fail']]) }}
            </x-alert>
        @endif

        @if ($counts['create'] === 0 && $counts['update'] === 0)
            <x-alert variant="error">{{ __('لا يوجد أي صف صالح في هذا الملف. أصلح الأخطاء وارفعه مرة أخرى.') }}</x-alert>
        @endif

        <x-card :title="__('أول :count صف', ['count' => count($rows)])" flush>
            @if ($truncated)
                <x-slot:actions>
                    <span class="u-subtle">{{ __('تُعالَج البقية بالقواعد نفسها') }}</span>
                </x-slot:actions>
            @endif

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('الصف') }}</th>
                            <th>{{ __('ما الذي سيحدث') }}</th>
                            @foreach ($batch->type->columns() as $column)
                                <th class="u-mono u-ltr">{{ $column }}</th>
                            @endforeach
                            <th>{{ __('السبب') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="u-nums">{{ $row['row'] }}</td>
                                <td class="import-row__action import-row--{{ $row['action'] }}">
                                    {{ ['create' => __('جديد'), 'update' => __('تحديث'), 'fail' => __('مرفوض')][$row['action']] }}
                                </td>
                                @foreach ($batch->type->columns() as $column)
                                    <td>
                                        @php $value = $row['data'][$column] ?? null; @endphp
                                        @if (is_bool($value))
                                            {{ $value ? __('مفعّل') : __('موقوف') }}
                                        @else
                                            {{ $value ?? '—' }}
                                        @endif
                                    </td>
                                @endforeach
                                <td class="u-subtle">{{ $row['reason'] ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="form-actions">
            <form method="POST" action="{{ route('admin.import.confirm', $batch) }}">
                @csrf
                <x-button variant="primary" :disabled="$counts['create'] === 0 && $counts['update'] === 0">
                    {{ __('تأكيد الاستيراد') }}
                </x-button>
            </form>
            <x-button variant="ghost" :href="route('admin.import.index')">{{ __('إلغاء') }}</x-button>
        </div>
    </div>
@endsection
