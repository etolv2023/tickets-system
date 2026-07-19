{{-- An internal ticket has no customer, so this card answers a different
     question: who on the team asked for it. --}}
<x-collapsible-card :title="$ticket->isInternal() ? 'طلب داخلي' : 'العميل'">
    <div class="facts">
        @if ($ticket->isInternal())
            <div class="facts__row">
                <span class="facts__label">طلبها</span>
                <span class="facts__value">{{ $ticket->requester?->name ?? '—' }}</span>
            </div>
        @else
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
        @endif
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
</x-collapsible-card>

{{-- F24: the link and password are only ever readable here, right after
     (re)generating — nobody, including the admin, can look up an old one. --}}
@can('update', $ticket)
    <x-collapsible-card :title="$ticket->isInternal() ? 'بوابة المتابعة' : 'بوابة العميل'">
        {{-- The link and the password both get a copy button rather than being
             printed for hand-transcription: the password is random and the URL
             is long, and both get pasted into a chat window anyway. --}}
        <div class="stack stack--tight">
            <x-copy-row label="لينك البوابة"
                        :value="route('portal.show', $ticket->ticket_number)" />

            @if (session('portalPassword'))
                <x-copy-row label="الباسورد الجديد"
                            :value="session('portalPassword')"
                            secret />
                <p class="field__hint">ابعته للعميل دلوقتي — مش هيتعرض تاني.</p>
            @endif

            <form method="POST" action="{{ route('tickets.portal.regenerate', $ticket) }}">
                @csrf
                <x-button variant="secondary" size="sm">
                    {{ $ticket->portal_password_hash ? 'ولّد باسورد جديد' : 'ولّد باسورد' }}
                </x-button>
            </form>
        </div>
    </x-collapsible-card>
@endcan

<x-collapsible-card title="الوقت">
    <div class="facts">
        <div class="facts__row">
            <span class="facts__label">الأولوية</span>
            <span class="facts__value">
                <x-badge :variant="$ticket->priority->variant()" :icon="$ticket->priority->icon()">{{ $ticket->priority->label() }}</x-badge>
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
</x-collapsible-card>

<x-collapsible-card title="الفريق">
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
        <div class="facts__row">
            <span class="facts__label">ديف أوبس</span>
            <span class="facts__value">{{ $ticket->devops?->name ?? '—' }}</span>
        </div>
    </div>

    {{-- Assignment, the start/finish buttons and the state machine arrive in
         phase 3 (F06/F07). --}}
</x-collapsible-card>
