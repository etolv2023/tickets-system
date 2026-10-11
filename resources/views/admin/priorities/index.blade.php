@extends('layouts.app')

@section('title', __('الأولويات'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('الأولويات') }}</h1>
                <p class="page-subtitle">
                    {{ __('الأولويات الأساسية (المميزة بـ «أساسية») تعتمد عليها الأتمتة — يمكن تغيير اسمها ولونها ومهلتها فقط. أما الأولويات التي تضيفها فيمكن حذفها ما لم تكن هناك تذاكر عليها حاليًا.') }}
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card :title="__('أولوية جديدة')">
            <form method="POST" action="{{ route('admin.priorities.store') }}" class="form-grid">
                @csrf
                <x-field name="key" :label="__('المفتاح')" required :hint="__('بالإنجليزية، مثل :example — لا يمكن تغييره لاحقًا.', ['example' => 'blocker'])" />
                <x-field name="name_ar" :label="__('الاسم بالعربية')" required />
                <x-field name="name_en" :label="__('الاسم بالإنجليزية')" dir="ltr" :hint="__('اختياري — إذا تُرك فارغًا يظهر الاسم العربي.')" />
                <x-field name="color" :label="__('اللون')" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="sla_hours" :label="__('مهلة SLA (بالساعات)')" type="number" required min="1" max="8760" />
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
                            <th>{{ __('الأولوية') }}</th>
                            <th colspan="3">{{ __('الاسم / اللون / المهلة') }}</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($priorities as $p)
                            <tr>
                                <td>
                                    <x-badge :variant="$p->color">{{ $p->label() }}</x-badge>
                                    <span class="u-subtle u-mono">{{ $p->key }}</span>
                                    @if ($p->is_system)
                                        <span class="u-subtle">— {{ __('أساسية') }}</span>
                                    @endif
                                </td>
                                <td colspan="3">
                                    <form method="POST" action="{{ route('admin.priorities.update', $p) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $p->name_ar }}" class="input opt-form__name" aria-label="{{ __('الاسم بالعربية') }}" placeholder="{{ __('الاسم بالعربية') }}">
                                        <input type="text" name="name_en" value="{{ $p->name_en }}" class="input opt-form__name" dir="ltr" aria-label="{{ __('الاسم بالإنجليزية') }}" placeholder="{{ __('الاسم بالإنجليزية') }}">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($p->color === $value)>{{ __($label) }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="sla_hours" value="{{ $p->sla_hours }}" min="1" max="8760"
                                               class="input opt-form__num" aria-label="{{ __('مهلة SLA (بالساعات)') }}">
                                        <span class="settings__unit">{{ __('ساعة') }}</span>
                                        <x-button variant="ghost" size="sm">{{ __('حفظ') }}</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($p->is_system)
                                        <form method="POST" action="{{ route('admin.priorities.destroy', $p) }}"
                                              onsubmit="return confirm(@js(__('هل أنت متأكد من حذف «:name»؟', ['name' => $p->label()])))">
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
