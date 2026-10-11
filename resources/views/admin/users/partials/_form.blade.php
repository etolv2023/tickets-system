@php $user = $user ?? null; @endphp

<div class="form-stack">
    <x-form-errors />

    <x-card :title="__('بيانات المستخدم')">
        <div class="form-stack">
            <div class="form-grid">
                <x-field name="name" :label="__('الاسم')" :value="$user?->name" required />
                <x-field name="email" :label="__('البريد الإلكتروني')" type="email" :value="$user?->email" required />
            </div>

            <div class="form-grid">
                <x-field name="role_id" :label="__('الدور')" required>
                    <select id="role_id" name="role_id" required
                            @class(['select', 'select--invalid' => $errors->has('role_id')])>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected((int) old('role_id', $user?->role_id) === $role->id)>
                                {{ $role->label() }}
                            </option>
                        @endforeach
                    </select>
                </x-field>
            </div>

            <x-field
                name="daily_capacity_hours"
                :label="__('السعة اليومية')"
                type="number"
                :value="$user?->daily_capacity_hours ?? 6"
                required
                step="0.5"
                min="0.5"
                max="24"
                :hint="__('الساعات الإنتاجية في اليوم. يحسب التقويم منها عبء العمل.')"
            />

            {{-- ★ (2026-08-19) F26.1 — الشخص نفسه بيقدر يحطها من /profile،
                 والأدمن بيقدر يحطها له من هنا — مفيش تعارض، نفس الخانة ونفس
                 التحقق. فاضية = مفيش منشن، والاسم بيتكتب نص عادي. --}}
            <x-field
                name="discord_user_id"
                :label="__('معرّف المستخدم في Discord')"
                :value="$user?->discord_user_id"
                inputmode="numeric"
                autocomplete="off"
                placeholder="709211234567890123"
                :hint="__('المعرّف الرقمي وليس اسم المستخدم — ليُشار إليه في Discord عند الاستثناءات. اتركه فارغًا ليضيفه الشخص بنفسه من صفحة «حسابي».')"
            />

            <label class="checkbox-row">
                <input type="checkbox" name="is_active" value="1" class="checkbox"
                       @checked(old('is_active', $user?->is_active ?? true))>
                <span class="checkbox-row__label">
                    {{ __('الحساب مفعّل') }}
                    <small>{{ __('لا يستطيع المستخدم الموقوف الدخول، لكن سجله يبقى محفوظًا.') }}</small>
                </span>
            </label>
        </div>
    </x-card>

    @isset($groups)
        {{-- ★ (2026-08-02) Per-user permission exceptions.

             The admin ticks what this person can actually do, starting from
             whatever their role already grants — not "add an exception" /
             "remove an exception", which is how the data is stored but not how
             anybody thinks about it. UserController saves only the boxes that
             DIFFER from the role, so the override table holds nothing but
             deliberate departures and a user left untouched keeps zero rows.

             Edit only: on create the role isn't saved yet, so there is no
             baseline to diff against. The new user starts on exactly their
             role's set and exceptions are added by editing them after. --}}
        <x-card :title="__('صلاحيات الشخص')">
            <x-slot:actions>
                <span class="u-subtle">{{ __('استثناءات إضافية على الدور') }}</span>
            </x-slot:actions>

            <p class="field__hint">
                {{ __('حدّد ما يستطيع :name فعله. العناصر المميزة بـ «من الدور» مصدرها دوره — إلغاء التحديد يمنعها عنه وحده، وتحديد عنصر إضافي يمنحه له وحده. لا يتأثر زملاؤه في الدور نفسه.', ['name' => $user->name]) }}
            </p>

            <x-permission-picker :groups="$groups" :selected="$effectivePermissionIds"
                                 :inherited="$rolePermissionIds" name="permissions" id-prefix="uperm" />
        </x-card>

        @include('admin.users.partials._waivers')
    @endisset

    @unless ($user)
        <x-card :title="__('كلمة المرور')">
            <div class="form-grid">
                <x-field name="password" :label="__('كلمة المرور')" type="password" required
                         :hint="__('10 أحرف على الأقل.')" autocomplete="new-password" />
                <x-field name="password_confirmation" :label="__('تأكيد كلمة المرور')" type="password" required
                         autocomplete="new-password" />
            </div>
        </x-card>
    @endunless

    <div class="form-actions">
        <x-button variant="primary">{{ $user ? __('حفظ التعديلات') : __('إضافة المستخدم') }}</x-button>
        <x-button variant="ghost" :href="route('admin.users.index')">{{ __('إلغاء') }}</x-button>
    </div>
</div>
