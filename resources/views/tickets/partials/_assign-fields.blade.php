{{-- The distribution block on the create form (F06.3). Assigning right away
     means the starter subtask exists from the ticket's first moment.

     ★ (2026-08-02) Was one dropdown per assignable role, all nine rendered at
     once and all reading "— لا يوجد —". Nine identical empty selects is the single
     biggest thing wrong with this page: it fills half the form to say nothing,
     and the two or three roles a real ticket actually needs are lost in it.

     Now it starts empty and you add the people you want, one row at a time. A
     role already taken drops out of the next row's list, so the same role can
     never be assigned twice — which the nine-select version couldn't express
     either way. --}}
<x-card :title="__('فريق التنفيذ ومسؤولياته')">
    <x-slot:actions>
        <span class="u-subtle" x-text="rows.length ? rows.length + ' ' + {{ Js::from(__('مُسند')) }} : {{ Js::from(__('لا يوجد')) }}"></span>
    </x-slot:actions>

    <div class="assign">
        <p class="field__hint">
            {{ __('اختر المسؤول عن كل تخصص مطلوب. الإسناد يحدد «من المسؤول وبأي صفة»، ثم ينشئ النظام مهمة بداية لمتابعة عمله. طلبات المزايا أو الوحدات الجديدة لا تبدأ قبل الموافقة الإدارية.') }}
        </p>

        <template x-for="(row, index) in rows" :key="index">
            <div class="assign__row">
                <div class="assign__field">
                    <label class="label" :for="`assign-role-${index}`">{{ __('المسؤولية المطلوبة') }}</label>
                    <select class="select" x-model="row.roleId" :id="`assign-role-${index}`"
                            @change="row.userId = ''">
                        <option value="">{{ __('— اختر المسؤولية —') }}</option>
                        <template x-for="role in rolesFor(row.roleId)" :key="role.id">
                            <option :value="role.id" x-text="role.name"></option>
                        </template>
                    </select>
                </div>

                <div class="assign__field">
                    <label class="label" :for="`assign-user-${index}`">{{ __('المسؤول') }}</label>
                    {{-- The name carries the role id, so the payload stays exactly
                         what the server already expects: role_assignments[ROLE]=USER. --}}
                    <select class="select" x-model="row.userId" :id="`assign-user-${index}`"
                            :name="row.roleId ? `role_assignments[${row.roleId}]` : ''"
                            :disabled="! row.roleId">
                        <option value="">{{ __('— اختر شخصًا —') }}</option>
                        <template x-for="person in candidatesFor(row.roleId)" :key="person.id">
                            <option :value="person.id" x-text="person.name"></option>
                        </template>
                    </select>
                </div>

                <button type="button" class="repeater__remove" @click="rows.splice(index, 1)"
                        aria-label="{{ __('إزالة المسؤولية') }}">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" fill="none" />
                    </svg>
                </button>
            </div>
        </template>

        <template x-if="! rows.length">
            <p class="assign__empty">{{ __('لم يُحدد فريق التنفيذ بعد. يمكن حفظ التذكرة الآن وإسناد المسؤوليات لاحقًا.') }}</p>
        </template>

        <x-button variant="ghost" size="sm" type="button"
                  @click="rows.push({ roleId: '', userId: '' })"
                  x-bind:disabled="rows.length >= roles.length">
            {{ __('+ إضافة مسؤولية') }}
        </x-button>
    </div>
</x-card>
