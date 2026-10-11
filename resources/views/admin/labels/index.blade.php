@extends('layouts.app')

@section('title', __('الوسوم'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('الوسوم') }}</h1>
                <p class="page-subtitle">{{ __('الألوان من اللوحة الدلالية فقط — لا توجد ألوان عشوائية.') }}</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('وسم جديد')">
            <form method="POST" action="{{ route('admin.labels.store') }}" class="form-grid">
                @csrf
                <x-field name="name" :label="__('الاسم بالعربية')" required />
                <x-field name="name_en" :label="__('الاسم بالإنجليزية')" dir="ltr" :hint="__('اختياري — إذا تُرك فارغًا يظهر الاسم العربي.')" />
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
                            <th>{{ __('الوسم') }}</th>
                            <th>{{ __('الاسم / اللون') }}</th>
                            <th>{{ __('التذاكر') }}</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($labels as $label)
                            <tr>
                                <td><x-badge :variant="$label->color">{{ $label->label() }}</x-badge></td>
                                <td>
                                    <form method="POST" action="{{ route('admin.labels.update', $label) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name" value="{{ $label->name }}" class="input opt-form__name" required aria-label="{{ __('الاسم بالعربية') }}" placeholder="{{ __('الاسم بالعربية') }}">
                                        <input type="text" name="name_en" value="{{ $label->name_en }}" class="input opt-form__name" dir="ltr" aria-label="{{ __('الاسم بالإنجليزية') }}" placeholder="{{ __('الاسم بالإنجليزية') }}">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $colorLabel)
                                                <option value="{{ $value }}" @selected($label->color === $value)>{{ __($colorLabel) }}</option>
                                            @endforeach
                                        </select>
                                        <x-button variant="ghost" size="sm">{{ __('حفظ') }}</x-button>
                                    </form>
                                </td>
                                <td class="u-nums">{{ $label->tickets_count }}</td>
                                <td class="table__cell--actions">
                                    <form method="POST" action="{{ route('admin.labels.destroy', $label) }}"
                                          onsubmit="return confirm(@js(__('سيُزال من :count تذكرة. هل أنت متأكد؟', ['count' => $label->tickets_count])))">
                                        @csrf
                                        @method('DELETE')
                                        <x-button variant="ghost" size="sm">{{ __('حذف') }}</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="4">{{ __('لا توجد وسوم.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
