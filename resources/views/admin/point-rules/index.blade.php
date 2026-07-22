@extends('layouts.app')

@section('title', 'مصفوفة النقاط')

@section('content')
    {{-- ★★★★ (2026-07-21): a single wide matrix table — layout.css's own
         "a single wide table" case for .page--wide. --}}
    <div class="page page--wide">
        <div class="page__head">
            <div>
                <h1 class="page-title">مصفوفة النقاط</h1>
                <p class="page-subtitle">
                    النقاط بتتصرف مرة واحدة أول ما التذكرة تتحل.
                </p>
            </div>
        </div>

        {{-- The one thing an admin must understand before touching this. F18 --}}
        <x-alert>
            <strong>التعديل هنا مبيأثرش بأثر رجعي.</strong>
            التذاكر الي اتحلت خلاص بتحتفظ بنقاطها زي ما هي — التغيير بيسري على الي هيتحل من دلوقتي بس.
            <br>
            الخانة الفاضية معناها إن الجهة دي مبتاخدش نقط على التركيبة دي.
        </x-alert>

        <div x-data="pointMatrix()">
            <p class="field__error" x-show="error" x-text="error" x-cloak></p>
            <p class="u-subtle" x-show="saved" x-text="saved" x-cloak></p>

            <x-card flush>
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>النوع</th>
                                <th>النطاق</th>
                                @foreach ($sides as $side)
                                    <th class="matrix__role-head">{{ $side->label() }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>
                                        <x-badge :variant="$row['type']->variant()">{{ $row['type']->label() }}</x-badge>
                                    </td>
                                    <td>{{ $scopeLabels[$row['scope']] }}</td>

                                    @foreach ($sides as $side)
                                        @php
                                            $key = "{$row['type']->value}|{$row['scope']}|{$side->value}";
                                            $rule = $rules[$key] ?? null;
                                        @endphp
                                        <td class="matrix__cell">
                                            @include('admin.point-rules.partials._cell', [
                                                'rule' => $rule,
                                                'type' => $row['type']->value,
                                                'scope' => $row['scope'],
                                                'side' => $side->value,
                                            ])
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            {{-- F06 role-assignment extension: every role opted into ticket
                 assignment earns the same way frontend/backend do — a Done
                 subtask tagged to the role pays it, per (ticket type, role). --}}
            @if ($roles->isNotEmpty())
                <x-card title="نقاط الأدوار الإضافية" :meta="'أي رول «تظهر في التوزيع» من إدارة الأدوار'">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>النوع</th>
                                    @foreach ($roles as $role)
                                        <th class="matrix__role-head">{{ $role->name_ar }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($roleTypes as $type)
                                    <tr>
                                        <td>
                                            <x-badge :variant="$type->variant()">{{ $type->label() }}</x-badge>
                                        </td>

                                        @foreach ($roles as $role)
                                            @php $rule = $roleRules["{$type->value}|{$role->id}"] ?? null; @endphp
                                            <td class="matrix__cell">
                                                @include('admin.point-rules.partials._role-cell', [
                                                    'rule' => $rule,
                                                    'type' => $type->value,
                                                    'roleId' => $role->id,
                                                ])
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif
        </div>

        {{-- F18: a manual correction — a new row, never an edit to what's already
             paid. Positive tops someone up, negative claws a mistake back. --}}
        <x-card title="تصحيح يدوي" :meta="'سطر جديد في دفتر النقاط — مش تعديل على المصروف'">
            <form method="POST" action="{{ route('admin.point-rules.corrections.store') }}" class="form-stack">
                @csrf

                <div class="form-grid">
                    <x-field name="user_id" label="المستخدم" required>
                        <select id="user_id" name="user_id" class="select" required>
                            <option value="">— اختار —</option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field name="side" label="الجهة" required>
                        <select id="side" name="side" class="select" required>
                            @foreach ($sides as $side)
                                <option value="{{ $side->value }}" @selected(old('side') === $side->value)>{{ $side->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <x-field name="points" label="النقاط" type="number" step="0.5" required
                             :value="old('points')"
                             hint="رقم موجب يضيف، رقم سالب يخصم." />

                    <x-field name="ticket_number" label="رقم التذكرة (اختياري)" :value="old('ticket_number')"
                             placeholder="TK-2026-00001" />
                </div>

                <x-field name="reason" label="السبب" required :value="old('reason')" />

                <div class="form-actions">
                    <x-button variant="primary" size="sm">سجّل التصحيح</x-button>
                </div>
            </form>
        </x-card>

        <x-card title="آخر التصحيحات" flush>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>المستخدم</th>
                            <th>الجهة</th>
                            <th class="table__cell--num">النقاط</th>
                            <th>السبب</th>
                            <th>التذكرة</th>
                            <th>سجّلها</th>
                            <th>التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($corrections as $row)
                            <tr>
                                <td>{{ $row->user->name }}</td>
                                <td>{{ $row->side->label() }}</td>
                                <td class="table__cell--num points-cell">{{ rtrim(rtrim($row->points, '0'), '.') }}</td>
                                <td class="u-subtle">{{ $row->reason }}</td>
                                <td>
                                    @if ($row->ticket)
                                        <a href="{{ route('tickets.show', $row->ticket) }}" class="u-mono u-ltr">
                                            {{ $row->ticket->ticket_number }}
                                        </a>
                                    @else
                                        <span class="u-subtle">—</span>
                                    @endif
                                </td>
                                <td class="u-subtle">{{ $row->correctedBy?->name }}</td>
                                <td class="u-subtle u-mono">{{ $row->created_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr class="table__empty"><td colspan="7">مفيش تصحيحات لسه.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
