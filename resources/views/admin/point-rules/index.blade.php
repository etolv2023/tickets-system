@extends('layouts.app')

@section('title', 'تصحيحات النقاط')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">تصحيحات النقاط</h1>
                <p class="page-subtitle">
                    كل صب تاسك بيتصرف بنقاطه هو — تتعدّل من جوه الصب تاسك نفسه. الشاشة دي للتصحيح اليدوي بس.
                </p>
            </div>
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
                                <td>{{ $row->role?->name_ar ?? $row->side?->label() ?? '—' }}</td>
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
