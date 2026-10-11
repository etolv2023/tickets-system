@php
    $role = $role ?? null;
    $isSystem = (bool) ($role?->is_system);
@endphp

<div class="form-stack">
    <x-form-errors />

    @if ($isSystem)
        <x-alert>
            هذا دور أساسي للنظام. يمكن تعديل الاسم والوصف والصلاحيات، أما الكود الداخلي فثابت لحماية سير العمل.
        </x-alert>
    @endif

    <x-card title="بيانات الدور">
        <div class="form-grid">
            <x-field name="name_ar" label="اسم الدور بالعربية" :value="$role?->name_ar" required />
            <x-field name="name_en" label="Role name in English" :value="$role?->name_en" dir="ltr" />
            <x-field name="description_ar" label="وصف المسؤولية بالعربية" :value="$role?->description_ar" hint="اشرح للمسؤول ما الذي يتولاه صاحب هذا الدور، وليس الصلاحيات التقنية." />
            <x-field name="description_en" label="Responsibility description in English" :value="$role?->description_en" dir="ltr" />

            @if ($isSystem)
                <x-field name="key" label="الكود">
                    <input type="text" id="key" class="input" value="{{ $role->key }}" dir="ltr" disabled>
                    {{-- Disabled inputs aren't submitted; RoleRequest still needs the
                         key to pass its unique check. --}}
                    <input type="hidden" name="key" value="{{ $role->key }}">
                </x-field>
            @else
                <x-field
                    name="key"
                    label="الكود"
                    :value="$role?->key"
                    required
                    dir="ltr"
                    hint="حروف إنجليزية صغيرة وأرقام و _ بس."
                />
            @endif
        </div>
    </x-card>

    {{-- A plain attribute of the role, deliberately separate from "الصلاحيات"
         below: this answers "does this role show up as an assignment target
         on a ticket", not "what can this role's members do". --}}
    <x-card title="خصائص الدور داخل سير العمل">
        <x-alert>
            هذه الخيارات تحدد كيف يشارك الدور في التذكرة. وهي مختلفة عن الصلاحيات أدناه التي تحدد ما يستطيع المستخدم فتحه أو تنفيذه في النظام.
        </x-alert>
        <label class="checkbox-row">
            <input type="checkbox" name="assignable_on_tickets" value="1" class="checkbox"
                   @checked(old('assignable_on_tickets', $role?->assignable_on_tickets))>
            <span class="checkbox-row__label">
                يمكن إسناد هذا الدور داخل التذكرة
                <small>يظهر الدور ضمن قائمة فريق التنفيذ، ويمكن اختيار شخص مسؤول عنه.</small>
            </span>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="logs_work" value="1" class="checkbox"
                   @checked(old('logs_work', $role?->logs_work))>
            <span class="checkbox-row__label">
                يتابع بدء العمل وانتهاءه
                <small>يحصل المسؤول على إجراءي «بدء العمل» و«إنهاء العمل»، وقد يؤثر اكتمال دوره في حالة التذكرة.</small>
            </span>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="is_tester" value="1" class="checkbox"
                   @checked(old('is_tester', $role?->is_tester))>
            <span class="checkbox-row__label">
                دور اعتماد جودة
                <small>تظهر التذكرة في قائمة الاختبار لهذا الدور بعد انتهاء التنفيذ الفني.</small>
            </span>
        </label>
    </x-card>

    <x-card title="صلاحيات الوصول والتنفيذ">
        <x-alert>
            الصلاحية تجيب عن سؤال «ما الذي يستطيع المستخدم فعله؟». يمكن أن يشترك أكثر من دور في الصلاحية نفسها، ولا تعني الصلاحية أن المستخدم مسؤول عن كل تذكرة.
        </x-alert>
        <x-slot:actions>
            <span class="u-subtle">{{ $groups->flatten()->count() }} صلاحية</span>
        </x-slot:actions>

        <x-permission-picker :groups="$groups" :selected="$selected" name="permissions" />
    </x-card>

    <div class="form-actions form-actions--sticky">
        <x-button variant="primary">{{ $role ? 'حفظ التعديلات' : 'إنشاء الدور' }}</x-button>
        <x-button variant="ghost" :href="route('admin.roles.index')">إلغاء</x-button>
    </div>
</div>
