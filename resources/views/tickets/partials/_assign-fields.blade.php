{{-- The distribution block on the create form (F06.3). Assigning right away
     means the starter subtask exists from the ticket's first moment.

     ★ (2026-08-02) Was one dropdown per assignable role, all nine rendered at
     once and all reading "— مفيش —". Nine identical empty selects is the single
     biggest thing wrong with this page: it fills half the form to say nothing,
     and the two or three roles a real ticket actually needs are lost in it.

     Now it starts empty and you add the people you want, one row at a time. A
     role already taken drops out of the next row's list, so the same role can
     never be assigned twice — which the nine-select version couldn't express
     either way. --}}
<x-card title="التوزيع">
    <x-slot:actions>
        <span class="u-subtle" x-text="rows.length ? `${rows.length} متسند` : 'مفيش'"></span>
    </x-slot:actions>

    <div class="assign">
        <p class="field__hint">
            مين هيشتغل على التذكرة دي؟ كل واحد بتضيفه بياخد صب تاسك بداية أول ما التوزيع يشتغل.
            لو التذكرة فيتشر أو موديول جديد، التوزيع بيستنى موافقة الأدمن الأول.
        </p>

        <template x-for="(row, index) in rows" :key="index">
            <div class="assign__row">
                <div class="assign__field">
                    <label class="label" :for="`assign-role-${index}`">الدور</label>
                    <select class="select" x-model="row.roleId" :id="`assign-role-${index}`"
                            @change="row.userId = ''">
                        <option value="">— اختار دور —</option>
                        <template x-for="role in rolesFor(row.roleId)" :key="role.id">
                            <option :value="role.id" x-text="role.name"></option>
                        </template>
                    </select>
                </div>

                <div class="assign__field">
                    <label class="label" :for="`assign-user-${index}`">الشخص</label>
                    {{-- The name carries the role id, so the payload stays exactly
                         what the server already expects: role_assignments[ROLE]=USER. --}}
                    <select class="select" x-model="row.userId" :id="`assign-user-${index}`"
                            :name="row.roleId ? `role_assignments[${row.roleId}]` : ''"
                            :disabled="! row.roleId">
                        <option value="">— اختار شخص —</option>
                        <template x-for="person in candidatesFor(row.roleId)" :key="person.id">
                            <option :value="person.id" x-text="person.name"></option>
                        </template>
                    </select>
                </div>

                <button type="button" class="repeater__remove" @click="rows.splice(index, 1)"
                        aria-label="شيل السطر">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" fill="none" />
                    </svg>
                </button>
            </div>
        </template>

        <template x-if="! rows.length">
            <p class="assign__empty">لسه محدش متسند. التذكرة تقدر تتفتح من غير توزيع وتتوزّع بعدين.</p>
        </template>

        <x-button variant="ghost" size="sm" type="button"
                  @click="rows.push({ roleId: '', userId: '' })"
                  x-bind:disabled="rows.length >= roles.length">
            + أضف شخص
        </x-button>
    </div>
</x-card>
