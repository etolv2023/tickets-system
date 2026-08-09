{{-- ★ (2026-08-04) The company, the reporter, their ERP number, the login code
     and the page link all moved to the summary strip under the title — they are
     what you need before you start, and a card that opens folded is the wrong
     place for that.

     What is left here is how to REACH the person, which you only want once you
     have something to tell them. Rendered at all only when there is a contact
     with contact details: an empty card is worse than no card. --}}
@if ($ticket->contact?->email || $ticket->contact?->phone)
    <x-collapsible-card title="التواصل مع المُبلغ" name="reporter-contact">
        <div class="facts">
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
@endif

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

{{-- ★ (2026-08-04) "الوقت" and "الفريق" merged into one card.

     Between them they held seven rows, and four of those now live somewhere
     better: priority, the age and the SLA deadline went to the summary strip
     (they decide whether you open the ticket at all, so they cannot be behind
     a fold), and "فتحها" was already printed in the "المشكلة" card's header —
     the same name, twice, on one screen.

     What was left was two cards of two or three rows each, which is what turns
     an aside into a column of grey title bars. --}}
<x-collapsible-card title="تفاصيل" name="ticket-details" :open="true">
    <div class="facts">
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

        {{-- Fully role-based distribution (2026-07-24): one row per assigned
             role, no hardcoded frontend/backend/tester/devops. --}}
        @forelse ($ticket->roleAssignments as $assignment)
            <div class="facts__row">
                <span class="facts__label">{{ $assignment->role->name_ar }}</span>
                <span class="facts__value">{{ $assignment->user?->name ?? '—' }}</span>
            </div>
        @empty
            <div class="facts__row">
                <span class="facts__label">التوزيع</span>
                <span class="facts__value">لسه متوزعتش</span>
            </div>
        @endforelse
    </div>
</x-collapsible-card>
