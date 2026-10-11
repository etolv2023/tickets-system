@extends('layouts.app')

@section('title', __('حالات المهام الفرعية'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('حالات المهام الفرعية') }}</h1>
                <p class="page-subtitle">
                    {{ __('الحالات الأساسية (المميزة بـ «أساسي») يمكن تغيير اسمها ولونها لكن لا يمكن حذفها، لأن لـ «مكتملة» و«قيد التنفيذ» و«متوقفة» سلوكًا في النظام (النقاط، ووقت البدء، والسبب). أما الحالات التي تضيفها فهي حالات عادية يمكن حذفها ما لم تكن هناك مهام فرعية عليها.') }}
                    <br>
                    <strong>{{ __('يتطلب سببًا:') }}</strong> {{ __('تتطلب هذه الحالة سببًا مكتوبًا (مثل «متوقفة»)، لذلك لا تظهر في التغيير السريع.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('حالة جديدة')">
            <form method="POST" action="{{ route('admin.subtask-statuses.store') }}" class="form-grid">
                @csrf
                <x-field name="key" :label="__('المفتاح')" required :hint="__('بالإنجليزية، مثل :example — لا يمكن تغييره لاحقًا.', ['example' => 'review'])" />
                <x-field name="name_ar" :label="__('الاسم بالعربية')" required />
                <x-field name="name_en" :label="__('الاسم بالإنجليزية')" dir="ltr" :hint="__('اختياري — إذا تُرك فارغًا يظهر الاسم العربي.')" />
                <x-field name="color" :label="__('اللون')" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-field>
                <label class="checkbox-row">
                    <input type="checkbox" name="needs_reason" value="1" class="checkbox">
                    <span class="checkbox-row__label">
                        {{ __('يتطلب سببًا') }}
                        <small>{{ __('تتطلب هذه الحالة سببًا مكتوبًا، ولا تظهر في التغيير السريع من البطاقة.') }}</small>
                    </span>
                </label>
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
                            <th>{{ __('الحالة') }}</th>
                            <th colspan="2">{{ __('الاسم / اللون / يتطلب سببًا') }}</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statuses as $s)
                            @php $value = \App\Casts\SubtaskStatusValue::for($s->key); @endphp
                            <tr>
                                <td>
                                    <x-badge :variant="$value->variant()">{{ $s->label() }}</x-badge>
                                    <span class="u-subtle u-mono">{{ $s->key }}</span>
                                    @if ($s->is_system)
                                        <span class="u-subtle">— {{ __('أساسي') }}</span>
                                    @endif
                                </td>
                                <td colspan="2">
                                    <form method="POST" action="{{ route('admin.subtask-statuses.update', $s) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $s->name_ar }}" class="input opt-form__name" aria-label="{{ __('الاسم بالعربية') }}" placeholder="{{ __('الاسم بالعربية') }}">
                                        <input type="text" name="name_en" value="{{ $s->name_en }}" class="input opt-form__name" dir="ltr" aria-label="{{ __('الاسم بالإنجليزية') }}" placeholder="{{ __('الاسم بالإنجليزية') }}">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($s->color === $value)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                        <label class="checkbox-row">
                                            <input type="checkbox" name="needs_reason" value="1" class="checkbox"
                                                   @checked($s->needs_reason) @disabled($s->is_system)>
                                            <span class="checkbox-row__label">{{ __('يتطلب سببًا') }}</span>
                                        </label>
                                        <x-button variant="ghost" size="sm">{{ __('حفظ') }}</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($s->is_system)
                                        <form method="POST" action="{{ route('admin.subtask-statuses.destroy', $s) }}"
                                              onsubmit="return confirm(@js(__('هل أنت متأكد من حذف «:name»؟', ['name' => $s->label()])))">
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
