{{-- The facts you need before you can start, directly under the title and never
     folded away.

     ★ (2026-08-04) These used to live in a collapsible aside card that opens
     closed, so "who is this for and where do I reproduce it" cost a scroll and
     a click on every single ticket. They are the first thing anyone asks, so
     they are the first thing on the page. --}}
<div class="ticket-summary">
    {{-- Urgency first. The status badge is already up in the header next to the
         title; priority was buried in a folded aside card called "الوقت", which
         is the last place anyone would look for it. --}}
    <div class="ticket-summary__item">
        <span class="ticket-summary__label">الأولوية</span>
        <span class="ticket-summary__value">
            <x-badge :variant="$ticket->priority->variant()"
                     :icon="$ticket->priority->icon()">{{ $ticket->priority->label() }}</x-badge>
        </span>
    </div>

    <div class="ticket-summary__item">
        <span class="ticket-summary__label">{{ $ticket->status->isOpen() ? 'العمر' : 'زمن الحل' }}</span>
        <span @class([
            'ticket-summary__value', 'u-mono',
            'ticket-summary__value--overdue' => $ticket->isOverdue(),
        ])>{{ $ticket->ageLabel() }}</span>
    </div>

    @if ($ticket->sla_due_at)
        <div class="ticket-summary__item">
            <span class="ticket-summary__label">مهلة الـ SLA</span>
            <span @class([
                'ticket-summary__value', 'u-mono',
                'ticket-summary__value--overdue' => $ticket->isOverdue(),
            ])>{{ $ticket->sla_due_at->timezone(config('app.display_timezone'))->translatedFormat('j M — H:i') }}</span>
        </div>
    @endif

    @if ($ticket->isInternal())
        <div class="ticket-summary__item">
            <span class="ticket-summary__label">طلبها</span>
            <span class="ticket-summary__value">{{ $ticket->requester?->name ?? '—' }}</span>
        </div>
    @else
        <div class="ticket-summary__item">
            <span class="ticket-summary__label">الشركة</span>
            <span class="ticket-summary__value">
                @can('companies.manage')
                    <a href="{{ route('admin.companies.show', $ticket->company) }}">{{ $ticket->company->name }}</a>
                @else
                    {{ $ticket->company->name }}
                @endcan
            </span>
        </div>

        <div class="ticket-summary__item">
            <span class="ticket-summary__label">المُبلغ</span>
            <span class="ticket-summary__value">
                {{ $ticket->reporter_name ?? '—' }}
                @if ($ticket->reporter_erp_id)
                    <span class="ticket-summary__sub u-mono u-ltr">{{ $ticket->reporter_erp_id }}</span>
                @endif
            </span>
        </div>
    @endif

    @if ($ticket->client_user_code)
        <div class="ticket-summary__item">
            <span class="ticket-summary__label">يوزر الدخول</span>
            {{-- A code, so monospace and LTR: it is read digit by digit and
                 compared against another screen. --}}
            <span class="ticket-summary__value u-mono u-ltr">{{ $ticket->client_user_code }}</span>
        </div>
    @endif

    @if ($ticket->page_url)
        <div class="ticket-summary__item ticket-summary__item--wide">
            <span class="ticket-summary__label">لينك الصفحة</span>
            <span class="ticket-summary__value">
                {{-- rel on an outbound link the client controls: without
                     noopener the target page gets a handle on this window. --}}
                <a class="ticket-summary__link u-ltr" href="{{ $ticket->page_url }}"
                   target="_blank" rel="noopener noreferrer nofollow"
                   title="{{ $ticket->page_url }}">
                    <x-icon name="external" size="0.9em" />
                    <span class="ticket-summary__url">{{ $ticket->page_url }}</span>
                </a>
            </span>
        </div>
    @endif
</div>
