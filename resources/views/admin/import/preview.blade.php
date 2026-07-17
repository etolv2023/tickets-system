@extends('layouts.app')

@section('title', 'معاينة الاستيراد')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">معاينة الاستيراد</h1>
                <p class="page-subtitle">
                    {{ $batch->original_filename }} · {{ $batch->type->label() }} · {{ $batch->total_rows }} صف
                </p>
            </div>
        </div>

        {{-- Nothing has been written yet. This is what the confirm button will do. --}}
        <x-alert>
            <strong>لسه مفيش أي صف اتكتب.</strong>
            الأرقام دي هي بالظبط الي هيحصل لو أكدت.
        </x-alert>

        <div class="import-summary">
            <div class="import-summary__item import-summary__item--create">
                <div class="import-summary__value">{{ $counts['create'] }}</div>
                <div class="import-summary__label">هيتعمل جديد</div>
            </div>
            <div class="import-summary__item import-summary__item--update">
                <div class="import-summary__value">{{ $counts['update'] }}</div>
                <div class="import-summary__label">هيتحدّث (موجود قبل كده)</div>
            </div>
            <div class="import-summary__item import-summary__item--fail">
                <div class="import-summary__value">{{ $counts['fail'] }}</div>
                <div class="import-summary__label">فيه خطأ — هيتخطى</div>
            </div>
        </div>

        @if ($counts['fail'] > 0)
            <x-alert variant="error">
                فيه {{ $counts['fail'] }} صف فيهم أخطاء. الصفوف دي هتتخطى والباقي هيكمل عادي.
            </x-alert>
        @endif

        @if ($counts['create'] === 0 && $counts['update'] === 0)
            <x-alert variant="error">مفيش أي صف صالح في الملف ده. صلّح الأخطاء وارفعه تاني.</x-alert>
        @endif

        <x-card title="أول {{ count($rows) }} صف" flush>
            @if ($truncated)
                <x-slot:actions>
                    <span class="u-subtle">الباقي هيتعالج بنفس القواعد</span>
                </x-slot:actions>
            @endif

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الصف</th>
                            <th>هيحصل إيه</th>
                            @foreach ($batch->type->columns() as $column)
                                <th class="u-mono u-ltr">{{ $column }}</th>
                            @endforeach
                            <th>السبب</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td class="u-nums">{{ $row['row'] }}</td>
                                <td class="import-row__action import-row--{{ $row['action'] }}">
                                    {{ ['create' => 'جديد', 'update' => 'تحديث', 'fail' => 'مرفوض'][$row['action']] }}
                                </td>
                                @foreach ($batch->type->columns() as $column)
                                    <td>
                                        @php $value = $row['data'][$column] ?? null; @endphp
                                        @if (is_bool($value))
                                            {{ $value ? 'مفعّل' : 'موقوف' }}
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
                    أكّد الاستيراد
                </x-button>
            </form>
            <x-button variant="ghost" :href="route('admin.import.index')">إلغاء</x-button>
        </div>
    </div>
@endsection
