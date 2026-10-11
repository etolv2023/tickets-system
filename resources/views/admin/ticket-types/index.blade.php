@extends('layouts.app')

@section('title', __('أنواع التذاكر'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('أنواع التذاكر') }}</h1>
                <p class="page-subtitle">
                    {{ __('الأنواع الأساسية (تحتها «أساسي») تقدر تغيّر اسمها/لونها/أيقونتها وتحدّد هل تحتاج موافقة — بس مينفعش تتحذف. اللي انت ضايفه تقدر تحذفه لو مفيش تذاكر واقفة عليه.') }}
                    <br>
                    <strong>{{ __('يحتاج موافقة:') }}</strong> {{ __('التذكرة بالنوع ده بتستنى موافقة الأدمن قبل ما تتوزع.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('نوع جديد')">
            <form method="POST" action="{{ route('admin.ticket-types.store') }}" class="form-grid">
                @csrf
                <x-field name="key" :label="__('المفتاح')" required :hint="__('إنجليزي، زي :example — ميتغيّرش بعد كده.', ['example' => 'task'])" />
                <x-field name="name_ar" :label="__('الاسم بالعربية')" required />
                <x-field name="name_en" :label="__('الاسم بالإنجليزية')" dir="ltr" :hint="__('اختياري — لو فاضي بيظهر الاسم العربي.')" />
                <x-field name="color" :label="__('اللون')" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="icon" :label="__('الأيقونة')" required>
                    <select id="icon" name="icon" class="select" required>
                        @foreach ($icons as $value => $label)
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="default_points" :label="__('النقاط الافتراضية')" required
                         type="number" step="0.25" min="0" max="999" :value="1"
                         :hint="__('النقطة اللي بتبدأ بيها كل صب تاسك على النوع ده — وتتعدّل بعدها بإيدك.')" />
                <label class="checkbox-row">
                    <input type="checkbox" name="needs_approval" value="1" class="checkbox">
                    <span class="checkbox-row__label">
                        {{ __('يحتاج موافقة الأدمن قبل التوزيع') }}
                        <small>{{ __('زي الفيتشر والموديول — التذكرة بتستنى موافقة قبل ما حد يشتغل عليها.') }}</small>
                    </span>
                </label>
                <div class="form-actions">
                    <x-button variant="primary">{{ __('أضف') }}</x-button>
                </div>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>{{ __('النوع') }}</th>
                            <th colspan="3">{{ __('الاسم / اللون / الأيقونة / الموافقة') }}</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $t)
                            @php $value = \App\Casts\TicketTypeValue::for($t->key); @endphp
                            <tr>
                                <td>
                                    <x-badge :variant="$value->variant()" :icon="$value->icon()">{{ $t->label() }}</x-badge>
                                    <span class="u-subtle u-mono">{{ $t->key }}</span>
                                    @if ($t->is_system)
                                        <span class="u-subtle">— {{ __('أساسي') }}</span>
                                    @endif
                                </td>
                                <td colspan="3">
                                    <form method="POST" action="{{ route('admin.ticket-types.update', $t) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $t->name_ar }}" class="input opt-form__name" aria-label="{{ __('الاسم بالعربية') }}" placeholder="{{ __('الاسم بالعربية') }}">
                                        <input type="text" name="name_en" value="{{ $t->name_en }}" class="input opt-form__name" dir="ltr" aria-label="{{ __('الاسم بالإنجليزية') }}" placeholder="{{ __('الاسم بالإنجليزية') }}">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($t->color === $value)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                        <select name="icon" class="select filters__select">
                                            @foreach ($icons as $value => $label)
                                                <option value="{{ $value }}" @selected($t->icon === $value)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="default_points" step="0.25" min="0" max="999"
                                               value="{{ (float) $t->default_points }}" aria-label="{{ __('النقاط الافتراضية') }}"
                                               class="input opt-form__num">
                                        <span class="settings__unit">{{ __('نقطة') }}</span>
                                        <label class="checkbox-row">
                                            <input type="checkbox" name="needs_approval" value="1" class="checkbox"
                                                   @checked($t->needs_approval)>
                                            <span class="checkbox-row__label">{{ __('يحتاج موافقة') }}</span>
                                        </label>
                                        <x-button variant="ghost" size="sm">{{ __('حفظ') }}</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($t->is_system)
                                        <form method="POST" action="{{ route('admin.ticket-types.destroy', $t) }}"
                                              onsubmit="return confirm(@js(__('متأكد من حذف «:name»؟', ['name' => $t->label()])))">
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
