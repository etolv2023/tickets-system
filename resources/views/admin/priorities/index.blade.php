@extends('layouts.app')

@section('title', 'الأولويات')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">الأولويات</h1>
                <p class="page-subtitle">
                    الأولويات الأساسية (تحتها رمادي فاتح) بتحرّكها الأتمتة نفسها — تقدر تغيّر اسمها/لونها/مهلتها
                    بس. الي انت ضايفها تقدر تحذفها لو مفيش تذاكر واقفة عليها دلوقتي.
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-card title="أولوية جديدة">
            <form method="POST" action="{{ route('admin.priorities.store') }}" class="form-grid">
                @csrf
                <x-field name="key" label="المفتاح" required hint="إنجليزي، زي blocker — ميتغيّرش بعد كده." />
                <x-field name="name_ar" label="الاسم" required />
                <x-field name="color" label="اللون" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="sla_hours" label="مهلة الـ SLA (ساعة)" type="number" required min="1" max="8760" />
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
                            <th>الأولوية</th>
                            <th colspan="3">اللون / المهلة</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($priorities as $p)
                            <tr>
                                <td>
                                    <x-badge :variant="$p->color">{{ $p->name_ar }}</x-badge>
                                    <span class="u-subtle u-mono">{{ $p->key }}</span>
                                    @if ($p->is_system)
                                        <span class="u-subtle">— أساسية</span>
                                    @endif
                                </td>
                                <td colspan="3">
                                    <form method="POST" action="{{ route('admin.priorities.update', $p) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $p->name_ar }}" class="input opt-form__name">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($p->color === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <input type="number" name="sla_hours" value="{{ $p->sla_hours }}" min="1" max="8760"
                                               class="input opt-form__num">
                                        <span class="settings__unit">ساعة</span>
                                        <x-button variant="ghost" size="sm">حفظ</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($p->is_system)
                                        <form method="POST" action="{{ route('admin.priorities.destroy', $p) }}"
                                              onsubmit="return confirm('متأكد من حذف «{{ $p->name_ar }}»؟')">
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
