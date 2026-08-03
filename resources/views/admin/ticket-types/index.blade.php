@extends('layouts.app')

@section('title', 'أنواع التذاكر')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">أنواع التذاكر</h1>
                <p class="page-subtitle">
                    الأنواع الأساسية (تحتها «أساسي») تقدر تغيّر اسمها/لونها/أيقونتها وتحدّد هل تحتاج موافقة —
                    بس مينفعش تتحذف. اللي انت ضايفه تقدر تحذفه لو مفيش تذاكر واقفة عليه.
                    <br>
                    <strong>يحتاج موافقة:</strong> التذكرة بالنوع ده بتستنى موافقة الأدمن قبل ما تتوزع (F15).
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card title="نوع جديد">
            <form method="POST" action="{{ route('admin.ticket-types.store') }}" class="form-grid">
                @csrf
                <x-field name="key" label="المفتاح" required hint="إنجليزي، زي task — ميتغيّرش بعد كده." />
                <x-field name="name_ar" label="الاسم" required />
                <x-field name="color" label="اللون" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="icon" label="الأيقونة" required>
                    <select id="icon" name="icon" class="select" required>
                        @foreach ($icons as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="default_points" label="النقاط الافتراضية" required
                         type="number" step="0.25" min="0" max="999" :value="1"
                         hint="النقطة اللي بتبدأ بيها كل صب تاسك على النوع ده — وتتعدّل بعدها بإيدك." />
                <label class="checkbox-row">
                    <input type="checkbox" name="needs_approval" value="1" class="checkbox">
                    <span class="checkbox-row__label">
                        يحتاج موافقة الأدمن قبل التوزيع
                        <small>زي الفيتشر والموديول — التذكرة بتستنى موافقة قبل ما حد يشتغل عليها.</small>
                    </span>
                </label>
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
                            <th colspan="3">اللون / الأيقونة / الموافقة</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $t)
                            @php $value = \App\Casts\TicketTypeValue::for($t->key); @endphp
                            <tr>
                                <td>
                                    <x-badge :variant="$value->variant()" :icon="$value->icon()">{{ $t->name_ar }}</x-badge>
                                    <span class="u-subtle u-mono">{{ $t->key }}</span>
                                    @if ($t->is_system)
                                        <span class="u-subtle">— أساسي</span>
                                    @endif
                                </td>
                                <td colspan="3">
                                    <form method="POST" action="{{ route('admin.ticket-types.update', $t) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $t->name_ar }}" class="input opt-form__name">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($t->color === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <select name="icon" class="select filters__select">
                                            @foreach ($icons as $value => $label)
                                                <option value="{{ $value }}" @selected($t->icon === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="default_points" step="0.25" min="0" max="999"
                                               value="{{ (float) $t->default_points }}" aria-label="النقاط الافتراضية"
                                               class="input opt-form__num">
                                        <span class="settings__unit">نقطة</span>
                                        <label class="checkbox-row">
                                            <input type="checkbox" name="needs_approval" value="1" class="checkbox"
                                                   @checked($t->needs_approval)>
                                            <span class="checkbox-row__label">يحتاج موافقة</span>
                                        </label>
                                        <x-button variant="ghost" size="sm">حفظ</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($t->is_system)
                                        <form method="POST" action="{{ route('admin.ticket-types.destroy', $t) }}"
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
