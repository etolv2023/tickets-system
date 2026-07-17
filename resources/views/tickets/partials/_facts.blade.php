<x-card title="العميل">
    <div class="facts">
        <div class="facts__row">
            <span class="facts__label">الشركة</span>
            <span class="facts__value">
                @can('companies.manage')
                    <a href="{{ route('admin.companies.show', $ticket->company) }}">{{ $ticket->company->name }}</a>
                @else
                    {{ $ticket->company->name }}
                @endcan
            </span>
        </div>
        <div class="facts__row">
            <span class="facts__label">المُبلغ</span>
            <span class="facts__value">{{ $ticket->reporter_name }}</span>
        </div>
        @if ($ticket->reporter_erp_id)
            <div class="facts__row">
                <span class="facts__label">رقم الـ ERP</span>
                <span class="facts__value u-mono u-ltr">{{ $ticket->reporter_erp_id }}</span>
            </div>
        @endif
        @if ($ticket->contact?->email)
            <div class="facts__row">
                <span class="facts__label">إيميل</span>
                <span class="facts__value u-ltr">{{ $ticket->contact->email }}</span>
            </div>
        @endif
        @if ($ticket->contact?->phone)
            <div class="facts__row">
                <span class="facts__label">تليفون</span>
                <span class="facts__value u-mono u-ltr">{{ $ticket->contact->phone }}</span>
            </div>
        @endif
    </div>
</x-card>

<x-card title="الوقت">
    <div class="facts">
        <div class="facts__row">
            <span class="facts__label">الأولوية</span>
            <span class="facts__value">
                <x-badge :variant="$ticket->priority->value">{{ $ticket->priority->label() }}</x-badge>
            </span>
        </div>
        <div class="facts__row">
            <span class="facts__label">{{ $ticket->status->isOpen() ? 'العمر' : 'زمن الحل' }}</span>
            <span @class(['facts__value', 'facts__value--num', 'facts__value--overdue' => $ticket->isOverdue()])>
                {{ $ticket->ageLabel() }}
            </span>
        </div>
        @if ($ticket->sla_due_at)
            <div class="facts__row">
                <span class="facts__label">مهلة الـ SLA</span>
                <span @class(['facts__value', 'facts__value--num', 'facts__value--overdue' => $ticket->isOverdue()])>
                    {{ $ticket->sla_due_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}
                </span>
            </div>
        @endif
        <div class="facts__row">
            <span class="facts__label">أول رد</span>
            <span class="facts__value facts__value--num">
                {{ $ticket->first_response_at
                    ? $ticket->first_response_at->timezone(config('app.display_timezone'))->diffForHumans($ticket->reported_at, short: true)
                    : 'لسه' }}
            </span>
        </div>
        @if ($ticket->resolved_at)
            <div class="facts__row">
                <span class="facts__label">اتحلت</span>
                <span class="facts__value facts__value--num">
                    {{ $ticket->resolved_at->timezone(config('app.display_timezone'))->translatedFormat('j M Y') }}
                </span>
            </div>
        @endif
    </div>
</x-card>

<x-card title="الفريق">
    <div class="facts">
        <div class="facts__row">
            <span class="facts__label">فتحها</span>
            <span class="facts__value">{{ $ticket->creator->name }}</span>
        </div>
        <div class="facts__row">
            <span class="facts__label">فرونت</span>
            <span class="facts__value">{{ $ticket->frontend?->name ?? '—' }}</span>
        </div>
        <div class="facts__row">
            <span class="facts__label">باك</span>
            <span class="facts__value">{{ $ticket->backend?->name ?? '—' }}</span>
        </div>
        <div class="facts__row">
            <span class="facts__label">تيستر</span>
            <span class="facts__value">{{ $ticket->tester?->name ?? '—' }}</span>
        </div>
    </div>

    {{-- Assignment, the start/finish buttons and the state machine arrive in
         phase 3 (F06/F07). --}}
</x-card>
