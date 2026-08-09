{{-- Company, contact and the reported time are the snapshot of who said what
     and when. Rendered as locked fields rather than a facts list: they sit in
     the same column as the editable ones, so the form reads as one shape and
     the lock is the only difference. F03

     Split out of _form.blade.php (2026-08-04) when that file passed the
     150-line limit — § 3. --}}
<x-card title="المُبلغ">
    <x-slot:actions>
        <span class="field__snapshot">
            <x-icon name="lock" size="0.9em" />
            لقطة ثابتة
        </span>
    </x-slot:actions>

    <div class="form-stack">
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="locked-company">
                    {{ $ticket->isInternal() ? 'طلبها' : 'الشركة' }}
                </label>
                <input id="locked-company" class="input input--locked" type="text"
                       value="{{ $ticket->originLabel() }}" readonly>
            </div>

            @unless ($ticket->isInternal())
                <div class="field">
                    <label class="field__label" for="locked-reporter">المُبلغ</label>
                    <input id="locked-reporter" class="input input--locked" type="text"
                           value="{{ $ticket->reporter_name }}{{ $ticket->reporter_erp_id ? " ({$ticket->reporter_erp_id})" : '' }}"
                           readonly>
                </div>
            @endunless
        </div>

        <div class="field">
            <label class="field__label" for="locked-reported-at">وقت الإبلاغ</label>
            <input id="locked-reported-at" class="input input--locked u-mono u-ltr" type="text"
                   value="{{ $ticket->reported_at->timezone(config('app.display_timezone'))->translatedFormat('j F Y — H:i') }}"
                   readonly>
        </div>

        <p class="field__hint">الحقول دي بتتسجّل مرة واحدة وقت فتح التذكرة ومبتتغيّرش.</p>
    </div>
</x-card>
