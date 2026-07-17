@extends('layouts.app')

@section('title', 'استيراد Excel')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">استيراد Excel</h1>
                <p class="page-subtitle">نزّل القالب، املاه، ارفعه. مفيش صف بيتكتب قبل ما تشوف المعاينة وتأكد.</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card title="1 — نزّل القالب" flush>
            @foreach ($types as $type)
                <div class="import-type">
                    <div>
                        <span class="import-type__name">{{ $type->label() }}</span>
                        <span class="import-type__cols">{{ implode(' · ', $type->columns()) }}</span>
                    </div>
                    <x-button variant="secondary" size="sm" :href="route('admin.import.template', $type->value)">
                        نزّل القالب
                    </x-button>
                </div>
            @endforeach
        </x-card>

        <x-card title="2 — ارفع الملف">
            <form method="POST" action="{{ route('admin.import.upload') }}" enctype="multipart/form-data" class="form-stack">
                @csrf

                <x-field name="type" label="نوع البيانات" required>
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
                    label="الملف"
                    type="file"
                    required
                    accept=".xlsx,.xls,.csv"
                    hint="xlsx أو xls أو csv · حد أقصى {{ number_format(\App\Services\ImportService::MAX_ROWS) }} صف."
                />

                <x-button variant="primary">ارفع وشوف المعاينة</x-button>
            </form>
        </x-card>

        @if ($batches->isNotEmpty())
            <x-card title="آخر عمليات الاستيراد" flush>
                <div class="table-wrap">
                    <table class="table table--hover">
                        <thead>
                            <tr>
                                <th>الملف</th>
                                <th>النوع</th>
                                <th>مين</th>
                                <th>اتعمل</th>
                                <th>اتحدّث</th>
                                <th>فشل</th>
                                <th>الحالة</th>
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
                                        <x-button variant="ghost" size="sm" :href="route('admin.import.report', $batch)">التقرير</x-button>
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
