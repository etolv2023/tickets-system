@php $contact = $contact ?? null; @endphp

<div class="form-stack">
    <x-form-errors />

    <x-card :title="__('بيانات جهة الاتصال')">
        <div class="form-stack">
            <div class="form-grid">
                <x-field name="name" :label="__('الاسم')" :value="$contact?->name" required />

                <x-field
                    name="erp_employee_id"
                    :label="__('رقم الموظف في نظام ERP')"
                    :value="$contact?->erp_employee_id"
                    required
                    dir="ltr"
                    :hint="__('لا يجوز تكراره داخل الشركة نفسها.')"
                />
            </div>

            <div class="form-grid">
                <x-field name="email" :label="__('البريد الإلكتروني')" type="email" :value="$contact?->email" />
                <x-field name="phone" :label="__('الهاتف')" :value="$contact?->phone" dir="ltr" />
            </div>

            <label class="checkbox-row">
                <input type="checkbox" name="is_active" value="1" class="checkbox"
                       @checked(old('is_active', $contact?->is_active ?? true))>
                <span class="checkbox-row__label">{{ __('جهة الاتصال مفعّلة') }}</span>
            </label>
        </div>
    </x-card>

    <div class="form-actions">
        <x-button variant="primary">{{ $contact ? __('حفظ التعديلات') : __('إضافة جهة الاتصال') }}</x-button>
        <x-button variant="ghost" :href="route('admin.companies.show', $company)">{{ __('إلغاء') }}</x-button>
    </div>
</div>
