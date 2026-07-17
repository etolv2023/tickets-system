@php $company = $company ?? null; @endphp

<div class="form-stack">
    <x-form-errors />

    <x-card title="بيانات الشركة">
        <div class="form-stack">
            <div class="form-grid">
                <x-field name="name" label="اسم الشركة" :value="$company?->name" required />

                <x-field
                    name="code"
                    label="الكود"
                    :value="$company?->code"
                    required
                    dir="ltr"
                    hint="حروف إنجليزية وأرقام و - و _ بس. ده الي شيت جهات الاتصال بيربط بيه."
                />
            </div>

            <x-field name="notes" label="ملاحظات">
                <textarea id="notes" name="notes" class="textarea" rows="3">{{ old('notes', $company?->notes) }}</textarea>
            </x-field>

            <label class="checkbox-row">
                <input type="checkbox" name="is_active" value="1" class="checkbox"
                       @checked(old('is_active', $company?->is_active ?? true))>
                <span class="checkbox-row__label">
                    الشركة مفعّلة
                    <small>الموقوفة متظهرش في قوائم فتح التذاكر، بس تذاكرها القديمة بتفضل شغالة.</small>
                </span>
            </label>
        </div>
    </x-card>

    <div class="form-actions">
        <x-button variant="primary">{{ $company ? 'حفظ التعديلات' : 'إضافة الشركة' }}</x-button>
        <x-button variant="ghost" :href="route('admin.companies.index')">إلغاء</x-button>
    </div>
</div>
