@extends('layouts.app')

@section('title', 'حالات التذاكر')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">حالات التذاكر</h1>
                <p class="page-subtitle">عرّف كل مرحلة باسم واضح ووصف يشرح معناها ونتيجتها. الحالات الأساسية مرتبطة بأتمتة النظام، أما الحالات المخصصة فتخدم الاستثناءات التشغيلية.</p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <x-workflow-guide />

        <x-card title="إضافة مرحلة مخصصة">
            <form method="POST" action="{{ route('admin.ticket-statuses.store') }}" class="form-grid">
                @csrf
                <x-field name="key" label="المفتاح الداخلي" required hint="حروف إنجليزية مثل on_hold، ولا يمكن تغييره لاحقًا." />
                <x-field name="name_ar" label="الاسم بالعربية" required />
                <x-field name="name_en" label="English name" dir="ltr" />
                <x-field name="description_ar" label="شرح المرحلة بالعربية" hint="متى تستخدم؟ وما النتيجة المتوقعة منها؟" />
                <x-field name="description_en" label="English description" dir="ltr" />
                <x-field name="color" label="اللون" required>
                    <select id="color" name="color" class="select" required>
                        @foreach ($colors as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="is_open" label="هل الالتزام ما زال قائمًا؟" hint="الحالة المفتوحة تعني أن العميل ما زال ينتظر إجراءً أو نتيجة.">
                    <select id="is_open" name="is_open" class="select" required>
                        <option value="1">مفتوحة</option>
                        <option value="0">مقفولة</option>
                    </select>
                </x-field>
                <div class="form-actions">
                    <x-button variant="primary">إضافة المرحلة</x-button>
                </div>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>الحالة</th>
                            <th>اللون</th>
                            <th>مفتوحة؟</th>
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($statuses as $s)
                            <tr>
                                <td>
                                    <x-badge :variant="$s->color">{{ $s->label() }}</x-badge>
                                    <span class="u-subtle u-mono">{{ $s->key }}</span>
                                    @if ($s->is_system)
                                        <span class="u-subtle">— مرتبطة بسير العمل الأساسي</span>
                                    @endif
                                </td>
                                <td colspan="2">
                                    <form method="POST" action="{{ route('admin.ticket-statuses.update', $s) }}" class="opt-form">
                                        @csrf
                                        @method('PUT')
                                        <input type="text" name="name_ar" value="{{ $s->name_ar }}" class="input opt-form__name" aria-label="الاسم بالعربية">
                                        <input type="text" name="name_en" value="{{ $s->name_en }}" class="input opt-form__name" dir="ltr" aria-label="English name">
                                        <input type="text" name="description_ar" value="{{ $s->description_ar }}" class="input" aria-label="شرح المرحلة بالعربية">
                                        <input type="hidden" name="description_en" value="{{ $s->description_en }}">
                                        <select name="color" class="select filters__select">
                                            @foreach ($colors as $value => $label)
                                                <option value="{{ $value }}" @selected($s->color === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <select name="is_open" class="select filters__select">
                                            <option value="1" @selected($s->is_open)>مفتوحة</option>
                                            <option value="0" @selected(! $s->is_open)>مقفولة</option>
                                        </select>
                                        <x-button variant="ghost" size="sm">حفظ</x-button>
                                    </form>
                                </td>
                                <td class="table__cell--actions">
                                    @unless ($s->is_system)
                                        <form method="POST" action="{{ route('admin.ticket-statuses.destroy', $s) }}"
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

        {{-- Was a 12x12 grid of 144 bare checkboxes. Nobody can read a matrix
             that size: the labels are only on the far edges, so by the time
             your eye reaches a cell you have lost which row and column it
             belongs to. Same data, one block per source status, each showing
             its own destinations by name. --}}
        <x-card title="الانتقالات المسموحة">
            <div class="flow__intro">
                <p><strong>ده بيتحكم في زرار «غيّر الحالة» على التذكرة.</strong></p>
                <p class="u-muted">
                    يحدد كل قسم المراحل التي يمكن الانتقال إليها يدويًا من المرحلة الحالية. الخيارات غير المحددة لن تظهر للمستخدم.
                </p>
                <p class="u-muted">
                    لا يلغي ذلك الأتمتة؛ ما زال النظام يحدّث المرحلة عند اكتمال العمل أو اعتماد الاختبار. هذه الخيارات للانتقالات اليدوية فقط.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.ticket-statuses.transitions') }}">
                @csrf
                @method('PUT')

                @foreach ($statuses as $from)
                    @php
                        $outgoing = $statuses->filter(fn ($to) => $to->id !== $from->id
                            && isset($edges["{$from->id}-{$to->id}"]))->count();
                    @endphp

                    <x-collapsible-section :title="'من ' . $from->label()"
                                           :meta="$outgoing . ' انتقال'"
                                           :open="false">
                        <div class="flow__targets">
                            @foreach ($statuses as $to)
                                @continue($from->id === $to->id)

                                <label class="flow__target">
                                    <input type="checkbox" class="checkbox"
                                           name="transitions[{{ $from->id }}][{{ $to->id }}]"
                                           value="1"
                                           @checked(isset($edges["{$from->id}-{$to->id}"]))>
                                    <span>{{ $to->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </x-collapsible-section>
                @endforeach

                <div class="form-actions form-actions--sticky">
                    <x-button variant="primary">حفظ الانتقالات</x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
