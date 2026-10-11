{{-- Two kinds of ticket, one form.

     A client ticket is owed to a customer: it needs a company, a contact or a
     reporter name, and it gets an SLA and a portal. An internal one is raised
     by the team lead or the CTO — there is no customer, so what it needs is
     simply who asked for it.

     The distinction is stored as the absence of a company, not as a flag, so
     the toggle below clears whichever side it is leaving. --}}
{{-- `internal` is NOT declared here. It lives on the wrapper in _form.blade.php,
     because the "الوصول إلى الصفحة" card two cards down has to read it too, and a
     scope that stops at this file cannot be seen from there. Alpine inherits
     parent state, so everything below still reads it exactly as before. --}}
<div x-data="{ contactChosen: @js((bool) old('contact_id')) }" class="form-stack">

    <div class="origin-toggle">
        <label class="origin-toggle__option" :class="{ 'is-active': ! internal }">
            <input type="radio" name="is_internal" value="0" class="checkbox"
                   x-model.boolean="internal" :checked="! internal">
            <span>
                <strong>{{ __('من عميل') }}</strong>
                <small>{{ __('تذكرة عليها التزام ومهلة SLA وبوابة للعميل.') }}</small>
            </span>
        </label>

        <label class="origin-toggle__option" :class="{ 'is-active': internal }">
            <input type="radio" name="is_internal" value="1" class="checkbox"
                   x-model.boolean="internal" :checked="internal">
            <span>
                <strong>{{ __('داخلية') }}</strong>
                <small>{{ __('عمل من قائد الفريق أو المدير التقني — دون عميل ودون مهلة.') }}</small>
            </span>
        </label>
    </div>

    {{-- Client side --}}
    <div x-show="! internal" x-cloak class="form-stack">
        <div class="form-grid">
            <x-combobox name="company_id" resource="companies" :label="__('الشركة')" required
                        id="company_id"
                        :placeholder="__('اكتب اسم الشركة أو كودها…')" />

            <x-combobox name="contact_id" resource="contacts" :label="__('جهة الاتصال')"
                        scope="company_id"
                        :placeholder="__('اكتب اسم جهة الاتصال…')"
                        :hint="__('اختر الشركة أولًا. إذا لم يكن المُبلِّغ في القائمة فاتركها واكتب اسمه أدناه.')"
                        @change="contactChosen = !! $event.target.value" />
        </div>

        <div x-show="!contactChosen" x-cloak>
            <x-field name="reporter_name" :label="__('اسم المُبلِّغ')"
                     :hint="__('سيُسجَّل في التذكرة كما هو، ولن يتغير بعد ذلك.')" />
        </div>
    </div>

    {{-- Internal side --}}
    <div x-show="internal" x-cloak>
        <x-combobox name="requested_by" resource="users" :label="__('طالب العمل')"
                    :placeholder="__('اكتب اسم زميل…')"
                    :hint="__('الشخص الذي طلب هذا العمل من الفريق.')" />
    </div>
</div>
