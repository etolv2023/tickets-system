{{-- Two kinds of ticket, one form.

     A client ticket is owed to a customer: it needs a company, a contact or a
     reporter name, and it gets an SLA and a portal. An internal one is raised
     by the team lead or the CTO — there is no customer, so what it needs is
     simply who asked for it.

     The distinction is stored as the absence of a company, not as a flag, so
     the toggle below clears whichever side it is leaving. --}}
<div x-data="{
        internal: @js((bool) old('is_internal', false)),
        contactChosen: @js((bool) old('contact_id')),
     }" class="form-stack">

    <div class="origin-toggle">
        <label class="origin-toggle__option" :class="{ 'is-active': ! internal }">
            <input type="radio" name="is_internal" value="0" class="checkbox"
                   x-model.boolean="internal" :checked="! internal">
            <span>
                <strong>من عميل</strong>
                <small>تذكرة عليها التزام ومهلة SLA وبوابة للعميل.</small>
            </span>
        </label>

        <label class="origin-toggle__option" :class="{ 'is-active': internal }">
            <input type="radio" name="is_internal" value="1" class="checkbox"
                   x-model.boolean="internal" :checked="internal">
            <span>
                <strong>داخلية</strong>
                <small>شغل من التيم ليد أو الـ CTO — من غير عميل ومن غير مهلة.</small>
            </span>
        </label>
    </div>

    {{-- Client side --}}
    <div x-show="! internal" x-cloak class="form-stack">
        <div class="form-grid">
            <x-combobox name="company_id" resource="companies" label="الشركة" required
                        id="company_id"
                        placeholder="اكتب اسم الشركة أو كودها…" />

            <x-combobox name="contact_id" resource="contacts" label="جهة الاتصال"
                        scope="company_id"
                        placeholder="اكتب اسم جهة الاتصال…"
                        hint="اختار شركة الأول. لو المُبلغ مش في القائمة سيبها واكتب اسمه تحت."
                        @change="contactChosen = !! $event.target.value" />
        </div>

        <div x-show="!contactChosen" x-cloak>
            <x-field name="reporter_name" label="اسم المُبلغ"
                     hint="هيتسجّل على التذكرة زي ما هو، وميتغيرش بعد كده." />
        </div>
    </div>

    {{-- Internal side --}}
    <div x-show="internal" x-cloak>
        <x-combobox name="requested_by" resource="users" label="طلبها مين"
                    placeholder="اكتب اسم زميل…"
                    hint="الشخص اللي طلب الشغل ده من التيم." />
    </div>
</div>
