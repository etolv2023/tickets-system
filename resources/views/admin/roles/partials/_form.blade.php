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

    {{-- Was one column of forty-odd checkboxes: no grouping you could see, no
         way to tell how much of a section was on, and a scroll to reach the
         save button. Now each group is its own panel with its own count and a
         select-all, and the panels flow into however many columns fit. --}}
    <x-card title="الصلاحيات">
        <x-slot:actions>
            <span class="u-subtle">{{ $groups->flatten()->count() }} صلاحية</span>
        </x-slot:actions>

        @foreach ($groups as $groupKey => $permissions)
            @php
                $chosen = old('permissions', $selected) ?? [];
                $onCount = $permissions->filter(fn ($p) => in_array($p->id, $chosen, false))->count();
            @endphp

            <div x-data="{
                total: {{ $permissions->count() }},
                on: {{ $onCount }},
                toggleAll(state) {
                    this.$refs.list.querySelectorAll('input[type=checkbox]')
                        .forEach(box => box.checked = state);
                    this.on = state ? this.total : 0;
                },
                recount() {
                    this.on = this.$refs.list.querySelectorAll(':checked').length;
                },
            }">
                <x-collapsible-section :title="$permissions->first()->groupLabel()">
                    <x-slot:meta>
                        <span x-text="on + '/' + total"></span>
                    </x-slot:meta>

                    <x-slot:actions>
                        <button type="button" class="fold__link"
                                @click="toggleAll(on < total)"
                                x-text="on < total ? 'حدد الكل' : 'امسح الكل'"></button>
                    </x-slot:actions>

                    <div class="perms__list" x-ref="list" @change="recount()">
                        @foreach ($permissions as $permission)
                            <label class="perms__item" for="perm-{{ $permission->id }}">
                                <input
                                    type="checkbox"
                                    id="perm-{{ $permission->id }}"
                                    name="permissions[]"
                                    value="{{ $permission->id }}"
                                    class="checkbox"
                                    @checked(in_array($permission->id, $chosen, false))
                                >
                                <span class="perms__label">
                                    {{ $permission->name_ar }}
                                    <span class="perms__key u-mono u-ltr">{{ $permission->key }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </x-collapsible-section>
            </div>
        @endforeach
    </x-card>

    <div class="form-actions form-actions--sticky">
        <x-button variant="primary">{{ $role ? 'حفظ التعديلات' : 'إنشاء الدور' }}</x-button>
        <x-button variant="ghost" :href="route('admin.roles.index')">إلغاء</x-button>
    </div>
</div>
