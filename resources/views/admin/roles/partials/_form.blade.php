@php
    $role = $role ?? null;
    $isSystem = (bool) ($role?->is_system);
@endphp

<div class="form-stack">
    <x-form-errors />

    @if ($isSystem)
        <x-alert>
            {{ __('هذا دور أساسي للنظام. يمكن تعديل الاسم والوصف والصلاحيات، أما الكود الداخلي فثابت لحماية سير العمل.') }}
        </x-alert>
    @endif

    <x-card :title="__('بيانات الدور')">
        <div class="form-grid">
            <x-field name="name_ar" :label="__('اسم الدور بالعربية')" :value="$role?->name_ar" required />
            <x-field name="name_en" label="Role name in English" :value="$role?->name_en" dir="ltr" />
            <x-field name="description_ar" :label="__('وصف المسؤولية بالعربية')" :value="$role?->description_ar" :hint="__('اشرح للمسؤول ما الذي يتولاه صاحب هذا الدور، وليس الصلاحيات التقنية.')" />
            <x-field name="description_en" label="Responsibility description in English" :value="$role?->description_en" dir="ltr" />

            @if ($isSystem)
                <x-field name="key" :label="__('الكود')">
                    <input type="text" id="key" class="input" value="{{ $role->key }}" dir="ltr" disabled>
                    {{-- Disabled inputs aren't submitted; RoleRequest still needs the
                         key to pass its unique check. --}}
                    <input type="hidden" name="key" value="{{ $role->key }}">
                </x-field>
            @else
                <x-field
                    name="key"
                    :label="__('الكود')"
                    :value="$role?->key"
                    required
                    dir="ltr"
                    :hint="__('أحرف إنجليزية صغيرة وأرقام و_ فقط.')"
                />
            @endif
        </div>
    </x-card>

    {{-- A plain attribute of the role, deliberately separate from "الصلاحيات"
         below: this answers "does this role show up as an assignment target
         on a ticket", not "what can this role's members do". --}}
    <x-card :title="__('خصائص الدور داخل سير العمل')">
        <x-alert>
            {{ __('هذه الخيارات تحدد كيف يشارك الدور في التذكرة. وهي مختلفة عن الصلاحيات أدناه التي تحدد ما يستطيع المستخدم فتحه أو تنفيذه في النظام.') }}
        </x-alert>
        <label class="checkbox-row">
            <input type="checkbox" name="assignable_on_tickets" value="1" class="checkbox"
                   @checked(old('assignable_on_tickets', $role?->assignable_on_tickets))>
            <span class="checkbox-row__label">
                {{ __('يمكن إسناد هذا الدور داخل التذكرة') }}
                <small>{{ __('يظهر الدور ضمن قائمة فريق التنفيذ، ويمكن اختيار شخص مسؤول عنه.') }}</small>
            </span>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="logs_work" value="1" class="checkbox"
                   @checked(old('logs_work', $role?->logs_work))>
            <span class="checkbox-row__label">
                {{ __('يتابع بدء العمل وانتهاءه') }}
                <small>{{ __('يحصل المسؤول على إجراءي «بدء العمل» و«إنهاء العمل»، وقد يؤثر اكتمال دوره في حالة التذكرة.') }}</small>
            </span>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="is_tester" value="1" class="checkbox"
                   @checked(old('is_tester', $role?->is_tester))>
            <span class="checkbox-row__label">
                {{ __('دور اعتماد جودة') }}
                <small>{{ __('تظهر التذكرة في قائمة الاختبار لهذا الدور بعد انتهاء التنفيذ الفني.') }}</small>
            </span>
        </label>
    </x-card>

    <x-card :title="__('صلاحيات الوصول والتنفيذ')">
        <x-alert>
            {{ __('الصلاحية تجيب عن سؤال «ما الذي يستطيع المستخدم فعله؟». يمكن أن يشترك أكثر من دور في الصلاحية نفسها، ولا تعني الصلاحية أن المستخدم مسؤول عن كل تذكرة.') }}
        </x-alert>
        <x-slot:actions>
            <span class="u-subtle">{{ trans_choice(':count صلاحية|صلاحية واحدة|صلاحيتان|:count صلاحيات|:count صلاحية|:count صلاحية', $groups->flatten()->count()) }}</span>
        </x-slot:actions>

        <x-permission-picker :groups="$groups" :selected="$selected" name="permissions" />
    </x-card>

    <div class="form-actions form-actions--sticky">
        <x-button variant="primary">{{ $role ? __('حفظ التعديلات') : __('إنشاء الدور') }}</x-button>
        <x-button variant="ghost" :href="route('admin.roles.index')">{{ __('إلغاء') }}</x-button>
    </div>
</div>
