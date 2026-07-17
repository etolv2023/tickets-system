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

    <x-card title="الصلاحيات" flush>
        <x-slot:actions>
            <span class="u-subtle">{{ $groups->flatten()->count() }} صلاحية</span>
        </x-slot:actions>

        @foreach ($groups as $groupKey => $permissions)
            <div class="card__header">
                <h3 class="card__title">{{ $permissions->first()->groupLabel() }}</h3>
            </div>

            <div class="settings__group">
                @foreach ($permissions as $permission)
                    <div class="settings__row">
                        <label class="checkbox-row" for="perm-{{ $permission->id }}">
                            <input
                                type="checkbox"
                                id="perm-{{ $permission->id }}"
                                name="permissions[]"
                                value="{{ $permission->id }}"
                                class="checkbox"
                                @checked(in_array($permission->id, old('permissions', $selected) ?? [], false))
                            >
                            <span class="checkbox-row__label">
                                {{ $permission->name_ar }}
                                <span class="matrix__key">{{ $permission->key }}</span>
                            </span>
                        </label>
                        <span></span>
                    </div>
                @endforeach
            </div>
        @endforeach
    </x-card>

    <div class="form-actions">
        <x-button variant="primary">{{ $role ? 'حفظ التعديلات' : 'إنشاء الدور' }}</x-button>
        <x-button variant="ghost" :href="route('admin.roles.index')">إلغاء</x-button>
    </div>
</div>
