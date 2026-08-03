@php
    $role = $role ?? null;
    $isSystem = (bool) ($role?->is_system);
@endphp

<div class="form-stack">
    <x-form-errors />

    @if ($isSystem)
        <x-alert>
            ده دور أساسي. الاسم والصلاحيات قابلين للتعديل، بس الكود ثابت لأن الكود بيتسمّى في الـ seeders.
        </x-alert>
    @endif

    <x-card title="بيانات الدور">
        <div class="form-grid">
            <x-field name="name_ar" label="اسم الدور" :value="$role?->name_ar" required />

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
    <x-card title="التوزيع">
        <label class="checkbox-row">
            <input type="checkbox" name="assignable_on_tickets" value="1" class="checkbox"
                   @checked(old('assignable_on_tickets', $role?->assignable_on_tickets))>
            <span class="checkbox-row__label">
                تظهر في بلوك التوزيع على التذكرة
                <small>يبقى للدور ده دروبداون في بلوك «التوزيع» — كل التوزيع بقى من الأدوار.</small>
            </span>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="logs_work" value="1" class="checkbox"
                   @checked(old('logs_work', $role?->logs_work))>
            <span class="checkbox-row__label">
                بيسجّل شغل (بدأت / خلصت)
                <small>لما حد يتوزّع عليه الدور ده، بياخد سجل شغل بزراري «بدأت» و«خلصت» — وبيحرّك حالة التذكرة (زي الفرونت والباك).</small>
            </span>
        </label>

        <label class="checkbox-row">
            <input type="checkbox" name="is_tester" value="1" class="checkbox"
                   @checked(old('is_tester', $role?->is_tester))>
            <span class="checkbox-row__label">
                دور تيست
                <small>التذكرة الموزّعة على الدور ده بتظهر في طابور التيست بعد ما التطوير يخلص (زي التيستر).</small>
            </span>
        </label>
    </x-card>

    <x-card title="الصلاحيات">
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
