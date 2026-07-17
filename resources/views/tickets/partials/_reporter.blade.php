{{-- $contactsByCompany is built in the controller. Selecting a company filters
     the contact list client-side — no round-trip, and no query in a view. --}}
<div
    x-data="{
        company: @js(old('company_id', '')),
        contact: @js(old('contact_id', '')),
        contacts: @js($contactsByCompany),
        get available() { return this.contacts[this.company] ?? []; },
    }"
    class="form-stack"
>
    <div class="form-grid">
        <x-field name="company_id" label="الشركة" required>
            <select id="company_id" name="company_id" required x-model="company"
                    @change="contact = ''"
                    @class(['select', 'select--invalid' => $errors->has('company_id')])>
                <option value="">اختار شركة…</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}">{{ $company->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="contact_id" label="جهة الاتصال"
                 hint="اختار شركة الأول. لو المُبلغ مش في القائمة سيبها واكتب اسمه تحت.">
            <select id="contact_id" name="contact_id" x-model="contact" :disabled="!company"
                    @class(['select', 'select--invalid' => $errors->has('contact_id')])>
                <option value="">— مش من القائمة —</option>
                <template x-for="item in available" :key="item.id">
                    <option :value="item.id" x-text="item.label"></option>
                </template>
            </select>
        </x-field>
    </div>

    {{-- Only asked for when the reporter isn't a saved contact. --}}
    <div x-show="!contact" x-cloak>
        <x-field name="reporter_name" label="اسم المُبلغ"
                 hint="هيتسجّل على التذكرة زي ما هو، وميتغيرش بعد كده." />
    </div>
</div>
