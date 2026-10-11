@extends('layouts.app')

@section('title', __('أنواع الروابط'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('أنواع الروابط') }}</h1>
                <p class="page-subtitle">
                    {{ __('يُقرأ كل نوع في اتجاهين: من التذكرة الأصلية ومن التذكرة الأخرى (مثل «يعطّل» / «معطَّلة بسبب»). الأنواع الأساسية (المميزة بـ «أساسي») يمكن تعديلها لكن لا يمكن حذفها — لأن لـ «يعطّل» سلوكًا في النظام (علامة التعطيل).') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('نوع ربط جديد')">
            <form method="POST" action="{{ route('admin.link-types.store') }}" class="form-grid">
                @csrf
                <x-field name="key" :label="__('المفتاح')" required :hint="__('بالإنجليزية، مثل :example — لا يمكن تغييره لاحقًا.', ['example' => 'split_from'])" />
                <x-field name="name_ar" :label="__('الاسم من التذكرة الأصلية (عربي)')" required :hint="__('مثل «يعطّل».')" />
                <x-field name="name_en" :label="__('الاسم من التذكرة الأصلية (إنجليزي)')" dir="ltr" :hint="__('مثل «Blocks» — اختياري.')" />
                <x-field name="inverse_label_ar" :label="__('الاسم العكسي (عربي)')" required :hint="__('مثل «معطَّلة بسبب» — يظهر في التذكرة الأخرى.')" />
                <x-field name="inverse_label_en" :label="__('الاسم العكسي (إنجليزي)')" dir="ltr" :hint="__('مثل «Blocked by» — اختياري.')" />
                <x-field name="color" :label="__('اللون')" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-field>
                <div class="form-actions">
                    <x-button variant="primary">{{ __('إضافة') }}</x-button>
                </div>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>{{ __('النوع') }}</th>
                            <th colspan="2">{{ __('الاسم / العكسي / اللون') }}</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $t)
                            @php $value = \App\Casts\LinkTypeValue::for($t->key); @endphp
                            <tr>
                                <td>
                                    <x-badge :variant="$value->variant()">{{ $value->label() }}</x-badge>
                                    <span class="u-subtle">↔ {{ $value->inverseLabel() }}</span>
                                    <span class="u-subtle u-mono">{{ $t->key }}</span>
                                    @if ($t->is_system)
                                        <span class="u-subtle">— {{ __('أساسي') }}</span>
                                    @endif
                                </td>
                                <td colspan="2">
                                    <form method="POST" action="{{ route('admin.link-types.update', $t) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $t->name_ar }}" class="input opt-form__name" aria-label="{{ __('الاسم من التذكرة الأصلية (عربي)') }}" placeholder="{{ __('الاسم من التذكرة الأصلية (عربي)') }}">
                                        <input type="text" name="name_en" value="{{ $t->name_en }}" class="input opt-form__name" dir="ltr" aria-label="{{ __('الاسم من التذكرة الأصلية (إنجليزي)') }}" placeholder="{{ __('الاسم من التذكرة الأصلية (إنجليزي)') }}">
                                        <input type="text" name="inverse_label_ar" value="{{ $t->inverse_label_ar }}" class="input opt-form__name" aria-label="{{ __('الاسم العكسي (عربي)') }}" placeholder="{{ __('الاسم العكسي (عربي)') }}">
                                        <input type="text" name="inverse_label_en" value="{{ $t->inverse_label_en }}" class="input opt-form__name" dir="ltr" aria-label="{{ __('الاسم العكسي (إنجليزي)') }}" placeholder="{{ __('الاسم العكسي (إنجليزي)') }}">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($t->color === $value)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                        <x-button variant="ghost" size="sm">{{ __('حفظ') }}</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($t->is_system)
                                        <form method="POST" action="{{ route('admin.link-types.destroy', $t) }}"
                                              onsubmit="return confirm(@js(__('هل أنت متأكد من حذف «:name»؟', ['name' => $t->label()])))">
                                            @csrf
                                            @method('DELETE')
                                            <x-button variant="ghost" size="sm">{{ __('حذف') }}</x-button>
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
