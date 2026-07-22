@extends('layouts.app')

@section('title', 'أنواع الروابط')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">أنواع الروابط</h1>
                <p class="page-subtitle">
                    كل نوع بيتقري باتجاهين: من التذكرة الأصلية ومن التذكرة التانية (مثلاً «بتبلوك» / «مبلوكة بـ»).
                    الأنواع الأساسية (تحتها «أساسي») تقدر تعدّلها بس مينفعش تتحذف — «بتبلوك» ليه سلوك في النظام (علامة التبلوك).
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card title="نوع ربط جديد">
            <form method="POST" action="{{ route('admin.link-types.store') }}" class="form-grid">
                @csrf
                <x-field name="key" label="المفتاح" required hint="إنجليزي، زي split_from — ميتغيّرش بعد كده." />
                <x-field name="name_ar" label="الاسم من التذكرة" required hint="زي «بتبلوك»." />
                <x-field name="inverse_label_ar" label="الاسم العكسي" required hint="زي «مبلوكة بـ» — بيظهر على التذكرة التانية." />
                <x-field name="color" label="اللون" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <div class="form-actions">
                    <x-button variant="primary">أضف</x-button>
                </div>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>النوع</th>
                            <th colspan="2">الاسم / العكسي / اللون</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $t)
                            @php $value = \App\Casts\LinkTypeValue::for($t->key); @endphp
                            <tr>
                                <td>
                                    <x-badge :variant="$value->variant()">{{ $t->name_ar }}</x-badge>
                                    <span class="u-subtle">↔ {{ $t->inverse_label_ar }}</span>
                                    <span class="u-subtle u-mono">{{ $t->key }}</span>
                                    @if ($t->is_system)
                                        <span class="u-subtle">— أساسي</span>
                                    @endif
                                </td>
                                <td colspan="2">
                                    <form method="POST" action="{{ route('admin.link-types.update', $t) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $t->name_ar }}" class="input opt-form__name" aria-label="الاسم">
                                        <input type="text" name="inverse_label_ar" value="{{ $t->inverse_label_ar }}" class="input opt-form__name" aria-label="الاسم العكسي">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($t->color === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <x-button variant="ghost" size="sm">حفظ</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($t->is_system)
                                        <form method="POST" action="{{ route('admin.link-types.destroy', $t) }}"
                                              onsubmit="return confirm('متأكد من حذف «{{ $t->name_ar }}»؟')">
                                            @csrf
                                            @method('DELETE')
                                            <x-button variant="ghost" size="sm">حذف</x-button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
