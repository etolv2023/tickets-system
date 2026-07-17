@extends('layouts.app')

@section('title', 'العطلات والإجازات')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">العطلات والإجازات</h1>
                <p class="page-subtitle">
                    من غير دول، الكاليندر بيقول إن حد عليه شغل وهو أصلاً مش موجود.
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-form-errors />

        <div class="form-grid">
            <x-card title="عطلة جديدة">
                <form method="POST" action="{{ route('admin.calendar.holidays.store') }}" class="form-stack">
                    @csrf
                    <x-field name="name" label="الاسم" required placeholder="عيد الفطر" />
                    <x-field name="date" label="التاريخ" type="date" required />
                    <label class="checkbox-row">
                        <input type="checkbox" name="is_recurring" value="1" class="checkbox">
                        <span class="checkbox-row__label">
                            بتتكرر كل سنة
                            <small>هتتحسب في نفس اليوم والشهر من كل سنة.</small>
                        </span>
                    </label>
                    <x-button variant="primary">أضف</x-button>
                </form>
            </x-card>

            <x-card title="إجازة جديدة">
                <form method="POST" action="{{ route('admin.calendar.leaves.store') }}" class="form-stack">
                    @csrf
                    <x-field name="user_id" label="الموظف" required>
                        <select id="user_id" name="user_id" class="select" required>
                            @foreach ($users as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                    </x-field>

                    <div class="form-grid">
                        <x-field name="start_date" label="من" type="date" required />
                        <x-field name="end_date" label="لـ" type="date" required />
                    </div>

                    <x-field name="type" label="النوع" required>
                        <select id="type" name="type" class="select" required>
                            <option value="annual">سنوية</option>
                            <option value="sick">مرضية</option>
                            <option value="other">أخرى</option>
                        </select>
                    </x-field>

                    <x-field name="note" label="ملاحظة" />
                    <x-button variant="primary">سجّل</x-button>
                </form>
            </x-card>
        </div>

        <x-card title="العطلات" flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr><th>الاسم</th><th>التاريخ</th><th>بتتكرر؟</th><th class="table__cell--actions"></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($holidays as $holiday)
                            <tr>
                                <td>{{ $holiday->name }}</td>
                                <td class="u-nums">{{ $holiday->date->translatedFormat('j F Y') }}</td>
                                <td>
                                    @if ($holiday->is_recurring)
                                        <x-badge variant="inquiry">كل سنة</x-badge>
                                    @else
                                        <span class="u-subtle">مرة واحدة</span>
                                    @endif
                                </td>
                                <td class="table__cell--actions">
                                    <form method="POST" action="{{ route('admin.calendar.holidays.destroy', $holiday) }}">
                                        @csrf @method('DELETE')
                                        <x-button variant="ghost" size="sm">حذف</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty"><td colspan="4">مفيش عطلات مسجّلة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="الإجازات" flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr><th>الموظف</th><th>من</th><th>لـ</th><th>النوع</th><th>سجّلها</th><th class="table__cell--actions"></th></tr>
                    </thead>
                    <tbody>
                        @forelse ($leaves as $leave)
                            <tr>
                                <td>
                                    <div class="row">
                                        <x-avatar :user="$leave->user" size="sm" />
                                        {{ $leave->user->name }}
                                    </div>
                                </td>
                                <td class="u-nums">{{ $leave->start_date->translatedFormat('j M Y') }}</td>
                                <td class="u-nums">{{ $leave->end_date->translatedFormat('j M Y') }}</td>
                                <td><x-badge variant="neutral">{{ $leave->typeLabel() }}</x-badge></td>
                                <td class="u-subtle">{{ $leave->approver?->name ?? '—' }}</td>
                                <td class="table__cell--actions">
                                    <form method="POST" action="{{ route('admin.calendar.leaves.destroy', $leave) }}">
                                        @csrf @method('DELETE')
                                        <x-button variant="ghost" size="sm">حذف</x-button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty"><td colspan="6">مفيش إجازات مسجّلة.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
