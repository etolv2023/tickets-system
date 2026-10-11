@extends('layouts.app')

@section('title', __('استيراد Excel'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('استيراد Excel') }}</h1>
                <p class="page-subtitle">{{ __('نزّل القالب، املاه، ارفعه. مفيش صف بيتكتب قبل ما تشوف المعاينة وتأكد.') }}</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('1 — نزّل القالب')" flush>
            @foreach ($types as $type)
                <div class="import-type">
                    <div>
                        <span class="import-type__name">{{ $type->label() }}</span>
                        <span class="import-type__cols">{{ implode(' · ', $type->columns()) }}</span>
                    </div>
                    <x-button variant="secondary" size="sm" :href="route('admin.import.template', $type->value)">
                        {{ __('نزّل القالب') }}
                    </x-button>
                </div>
            @endforeach
        </x-card>

        <x-card :title="__('2 — ارفع الملف')">
            <form method="POST" action="{{ route('admin.import.upload') }}" enctype="multipart/form-data" class="form-stack">
                @csrf

                <x-field name="type" :label="__('نوع البيانات')" required>
                    <select id="type" name="type" required
                            @class(['select', 'select--invalid' => $errors->has('type')])>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected(old('type') === $type->value)>
                                {{ $type->label() }}
                            </option>
                        @endforeach
                    </select>
                </x-field>

                <x-field
                    name="file"
                    :label="__('الملف')"
                    type="file"
                    required
                    accept=".xlsx,.xls,.csv"
                    :hint="__('xlsx أو xls أو csv · حد أقصى :rows صف.', ['rows' => number_format(\App\Services\ImportService::MAX_ROWS)])"
                />

                <x-button variant="primary">{{ __('ارفع وشوف المعاينة') }}</x-button>
            </form>
        </x-card>

        @if ($batches->isNotEmpty())
            <x-card :title="__('آخر عمليات الاستيراد')" flush>
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>{{ __('الملف') }}</th>
                                <th>{{ __('النوع') }}</th>
                                <th>{{ __('مين') }}</th>
                                <th>{{ __('اتعمل') }}</th>
                                <th>{{ __('اتحدّث') }}</th>
                                <th>{{ __('فشل') }}</th>
                                <th>{{ __('الحالة') }}</th>
                                <th class="table__cell--actions"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($batches as $batch)
                                <tr>
                                    <td>{{ $batch->original_filename }}</td>
                                    <td>{{ $batch->type->label() }}</td>
                                    <td>{{ $batch->user->name }}</td>
                                    <td class="u-nums">{{ $batch->created_rows }}</td>
                                    <td class="u-nums">{{ $batch->updated_rows }}</td>
                                    <td class="u-nums">{{ $batch->failed_rows }}</td>
                                    <td><x-badge :variant="$batch->statusVariant()">{{ $batch->statusLabel() }}</x-badge></td>
                                    <td class="table__cell--actions">
                                        <x-button variant="ghost" size="sm" :href="route('admin.import.report', $batch)">{{ __('التقرير') }}</x-button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        @endif
    </div>
@endsection
