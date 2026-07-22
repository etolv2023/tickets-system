@extends('layouts.app')

@section('title', 'حالات الصب تاسك')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">حالات الصب تاسك</h1>
                <p class="page-subtitle">
                    الحالات الأساسية (تحتها «أساسي») تقدر تغيّر اسمها ولونها بس مينفعش تتحذف —
                    لأن «خلصت» و«جاري العمل» و«متوقفة» ليهم سلوك في النظام (النقاط، وقت البدء، السبب).
                    اللي انت ضايفه حالة عادية تقدر تحذفها لو مفيش صب تاسكس واقفة عليها.
                    <br>
                    <strong>يحتاج سبب:</strong> الحالة دي بتطلب سبب مكتوب (زي «متوقفة»)، وعشان كده مش بتظهر في التغيير السريع.
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card title="حالة جديدة">
            <form method="POST" action="{{ route('admin.subtask-statuses.store') }}" class="form-grid">
                @csrf
                <x-field name="key" label="المفتاح" required hint="إنجليزي، زي review — ميتغيّرش بعد كده." />
                <x-field name="name_ar" label="الاسم" required />
                <x-field name="color" label="اللون" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <label class="checkbox-row">
                    <input type="checkbox" name="needs_reason" value="1" class="checkbox">
                    <span class="checkbox-row__label">
                        يحتاج سبب
                        <small>الحالة دي بتطلب سبب مكتوب، ومش بتظهر في التغيير السريع من الكارت.</small>
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
                            <th>الحالة</th>
                            <th colspan="2">اللون / يحتاج سبب</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statuses as $s)
                            @php $value = \App\Casts\SubtaskStatusValue::for($s->key); @endphp
                            <tr>
                                <td>
                                    <x-badge :variant="$value->variant()">{{ $s->name_ar }}</x-badge>
                                    <span class="u-subtle u-mono">{{ $s->key }}</span>
                                    @if ($s->is_system)
                                        <span class="u-subtle">— أساسي</span>
                                    @endif
                                </td>
                                <td colspan="2">
                                    <form method="POST" action="{{ route('admin.subtask-statuses.update', $s) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $s->name_ar }}" class="input opt-form__name">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($s->color === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <label class="checkbox-row">
                                            <input type="checkbox" name="needs_reason" value="1" class="checkbox"
                                                   @checked($s->needs_reason) @disabled($s->is_system)>
                                            <span class="checkbox-row__label">يحتاج سبب</span>
                                        </label>
                                        <x-button variant="ghost" size="sm">حفظ</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($s->is_system)
                                        <form method="POST" action="{{ route('admin.subtask-statuses.destroy', $s) }}"
                                              onsubmit="return confirm('متأكد من حذف «{{ $s->name_ar }}»؟')">
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
