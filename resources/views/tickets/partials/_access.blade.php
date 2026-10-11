{{-- How whoever picks this up reproduces the problem: the account they sign in
     with, and the page it happens on.

     Kept out of the description on purpose — prose is where these used to live,
     and a link buried in a paragraph is not something the ticket page can lift
     to the top or the board can act on.

     Split out of _form.blade.php (2026-08-04) when that file passed the
     150-line limit — § 3. It reads `internal` from the wrapper _form declares,
     which Alpine inherits down the tree. --}}
<x-card :title="__('الوصول إلى الصفحة')">
    <div class="form-grid">
        <x-field name="client_user_code" :label="__('مستخدم الدخول')" type="number"
                 :value="$ticket?->client_user_code"
                 :required="$ticket && ! $ticket->isInternal()"
                 :required-expr="$ticket ? null : '! internal'"
                 inputmode="numeric"
                 placeholder="10452"
                 :hint="__('رقم المستخدم الذي تسجّل به الدخول إلى نظام العميل.')" />

        <x-field name="page_url" :label="__('رابط الصفحة')" type="url"
                 :value="$ticket?->page_url"
                 :required="$ticket && ! $ticket->isInternal()"
                 :required-expr="$ticket ? null : '! internal'"
                 dir="ltr"
                 placeholder="https://erp.example.com/invoices/123"
                 :hint="__('الصفحة التي تظهر فيها المشكلة تحديدًا، وليست الصفحة الرئيسية.')" />
    </div>

    @if (! $ticket)
        <p class="field__hint" x-show="internal" x-cloak>
            {{ __('اختياريان في التذكرة الداخلية — املأهما إذا كانت هناك صفحة فعلًا.') }}
        </p>
    @endif
</x-card>
