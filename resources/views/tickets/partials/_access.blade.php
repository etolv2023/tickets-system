{{-- How whoever picks this up reproduces the problem: the account they sign in
     with, and the page it happens on.

     Kept out of the description on purpose — prose is where these used to live,
     and a link buried in a paragraph is not something the ticket page can lift
     to the top or the board can act on.

     Split out of _form.blade.php (2026-08-04) when that file passed the
     150-line limit — § 3. It reads `internal` from the wrapper _form declares,
     which Alpine inherits down the tree. --}}
<x-card title="الوصول للصفحة">
    <div class="form-grid">
        <x-field name="client_user_code" label="يوزر الدخول" type="number"
                 :value="$ticket?->client_user_code"
                 :required="$ticket && ! $ticket->isInternal()"
                 :required-expr="$ticket ? null : '! internal'"
                 inputmode="numeric"
                 placeholder="10452"
                 hint="رقم اليوزر اللي بتفتح بيه سيستم العميل." />

        <x-field name="page_url" label="لينك الصفحة" type="url"
                 :value="$ticket?->page_url"
                 :required="$ticket && ! $ticket->isInternal()"
                 :required-expr="$ticket ? null : '! internal'"
                 dir="ltr"
                 placeholder="https://erp.example.com/invoices/123"
                 hint="الصفحة اللي فيها المشكلة بالظبط، مش الصفحة الرئيسية." />
    </div>

    @if (! $ticket)
        <p class="field__hint" x-show="internal" x-cloak>
            اختياريين في التذكرة الداخلية — املاهم لو فيه صفحة فعلاً.
        </p>
    @endif
</x-card>
