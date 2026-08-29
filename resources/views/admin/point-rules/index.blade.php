@extends('layouts.app')

@section('title', 'تصحيحات النقاط')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">تصحيحات النقاط</h1>
                <p class="page-subtitle">
                    كل صب تاسك بيتصرف بنقاطه هو — تتعدّل من جوه الصب تاسك نفسه. الشاشة دي للتصحيح اليدوي بس.
                    <br>
                    الإلغاء والتعديل بيتكتبوا كسطر عكسي — <strong>مفيش سطر بيتمسح من الدفتر</strong>، عشان تقرير أي شهر
                    يفضل مطابق للي اتصرف فيه.
                </p>
            </div>
        </div>

        {{-- ★ (2026-08-29) These were being set and never rendered: storeCorrection
             has always returned with('status'), and the screen had nowhere to
             print it. With cancel and edit added, three actions would have been
             silent instead of one. --}}
        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- One error block, not two: <x-form-errors> already prints whatever
             is in the bag, and a refusal ("ده سطر إلغاء") arrives in the same
             bag as a validation message. Rendering both showed every refusal
             twice. --}}
        <x-form-errors />

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

                    <x-field name="role_id" label="الدور" required>
                        <select id="role_id" name="role_id" class="select" required>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}" @selected((int) old('role_id') === $role->id)>{{ $role->name_ar }}</option>
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

        {{-- ★ (2026-08-29) «تعديل» و«إلغاء».

             Neither erases anything: cancelling writes a reversing row and
             editing writes that plus a corrected one. The cancelled original
             stays in the list, struck through — the sum stays right because the
             reversal is part of it, and a report printed last month still
             matches what was paid. --}}
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
                            <th class="table__cell--actions"></th>
                        </tr>
                    </thead>
                    {{-- One open editor at a time, keyed by row id: two forms
                         open at once on a money screen is two chances to submit
                         the one you were not looking at. --}}
                    <tbody x-data="{ editing: null }">
                        @forelse ($corrections as $row)
                            @include('admin.point-rules.partials._correction-row', ['correction' => $row])
                        @empty
                            <tr class="table__empty"><td colspan="8">مفيش تصحيحات لسه.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
