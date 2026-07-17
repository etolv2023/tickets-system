@php $contact = $contact ?? null; @endphp

<div class="form-stack">
    <x-form-errors />

    <x-card title="بيانات جهة الاتصال">
        <div class="form-stack">
            <div class="form-grid">
                <x-field name="name" label="الاسم" :value="$contact?->name" required />

                <x-field
                    name="erp_employee_id"
                    label="رقم الموظف في الـ ERP"
                    :value="$contact?->erp_employee_id"
                    required
                    dir="ltr"
                    hint="مينفعش يتكرر داخل نفس الشركة."
                />
            </div>

            <div class="form-grid">
                <x-field name="email" label="الإيميل" type="email" :value="$contact?->email" />
                <x-field name="phone" label="التليفون" :value="$contact?->phone" dir="ltr" />
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="is_active" value="1" class="checkbox"
                       @checked(old('is_active', $contact?->is_active ?? true))>
                <span class="checkbox-row__label">جهة الاتصال مفعّلة</span>
            </label>
        </div>
    </x-card>

    <div class="form-actions">
        <x-button variant="primary">{{ $contact ? 'حفظ التعديلات' : 'إضافة جهة الاتصال' }}</x-button>
        <x-button variant="ghost" :href="route('admin.companies.show', $company)">إلغاء</x-button>
    </div>
</div>
