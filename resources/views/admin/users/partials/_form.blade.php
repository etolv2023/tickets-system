@php $user = $user ?? null; @endphp

<div class="form-stack">
    <x-form-errors />

    <x-card title="بيانات المستخدم">
        <div class="form-stack">
            <div class="form-grid">
                <x-field name="name" label="الاسم" :value="$user?->name" required />
                <x-field name="email" label="الإيميل" type="email" :value="$user?->email" required />
            </div>

            <div class="form-grid">
                <x-field name="role_id" label="الدور" required>
                    <select id="role_id" name="role_id" required
                            @class(['select', 'select--invalid' => $errors->has('role_id')])>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((int) old('role_id', $user?->role_id) === $role->id)>
                                {{ $role->name_ar }}
                            </option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <x-field
                name="daily_capacity_hours"
                label="السعة اليومية"
                type="number"
                :value="$user?->daily_capacity_hours ?? 6"
                required
                step="0.5"
                min="0.5"
                max="24"
                hint="ساعات إنتاجية في اليوم. الكاليندر بيحسب منها الحمل."
            />

            <label class="checkbox-row">
                <input type="checkbox" name="is_active" value="1" class="checkbox"
                       @checked(old('is_active', $user?->is_active ?? true))>
                <span class="checkbox-row__label">
                    الحساب مفعّل
                    <small>الموقوف ميقدرش يدخل، بس تاريخه بيفضل موجود.</small>
                </span>
            </label>
        </div>
    </x-card>

    @unless ($user)
        <x-card title="كلمة السر">
            <div class="form-grid">
                <x-field name="password" label="كلمة السر" type="password" required
                         hint="10 حروف على الأقل." autocomplete="new-password" />
                <x-field name="password_confirmation" label="تأكيد كلمة السر" type="password" required
                         autocomplete="new-password" />
            </div>
        </x-card>
    @endunless

    <div class="form-actions">
        <x-button variant="primary">{{ $user ? 'حفظ التعديلات' : 'إضافة المستخدم' }}</x-button>
        <x-button variant="ghost" :href="route('admin.users.index')">إلغاء</x-button>
    </div>
</div>
