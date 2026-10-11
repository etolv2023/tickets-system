{{-- F08 — planning the work while opening the ticket, instead of opening it and
     then going to find the subtasks tab.

     Three fields per row on purpose. The full subtask form has eight (points,
     hours, blocked reason…), and most of them are meaningless for work that
     has not started: a subtask created with its ticket has no spent hours and
     is not blocked.

     ★ (2026-08-02) The "الدور" select became "صاحبها", and it is no longer
     decorative: store() turns it into the row's assignee_id. A subtask with no
     assignee is invisible to the point engine forever — TK-2026-00169 was
     resolved with seven of them and paid nobody. So the options are exactly the
     people being assigned in the block above, and:

       - nobody assigned yet  → no rows can be added at all
       - exactly one role     → it is the owner, no question asked
       - more than one        → the row must say which, every time

     Leaving the whole thing empty is still fine: blank rows are dropped in
     StoreTicketRequest before the rules run. --}}
<x-card :title="__('المهام الفرعية (اختياري)')">
    {{-- `subtaskRows`, not `rows`: the distribution block one scope up already
         owns a `rows`, and Alpine resolves a name against the innermost scope
         that has it — including inside a parent method, because `this` there is
         the merged proxy of the *call site*. Naming this one `rows` made the
         parent's assignedRoleIds() read the subtask rows and always return [],
         which locked the add button shut no matter who was assigned. --}}
    <div class="repeater"
         x-data="{
             subtaskRows: @js(array_values(old('subtasks', []))),
             add() { this.subtaskRows.push({ title: '', role_id: '', due_date: '' }) },
         }">
        <p class="repeater__lead">
            {{ __('قسّم العمل الآن إن أردت. يمكنك إضافتها أو تعديلها لاحقًا من صفحة التذكرة، وتأخذ كل مهمة فرعية نقاط النوع افتراضيًا ويمكن تعديلها منفردة.') }}
        </p>

        <template x-if="assignedRoleIds().length === 0">
            <p class="field__hint">{{ __('أسند العمل أعلاه أولًا — يجب أن يكون للمهمة الفرعية صاحب ليحصل على نقاطها.') }}</p>
        </template>

        <template x-for="(row, index) in subtaskRows" :key="index">
            <div class="repeater__row">
                <div class="repeater__field repeater__field--grow">
                    <label class="label" :for="`subtask-title-${index}`">{{ __('العنوان') }}</label>
                    <input type="text" class="input" x-model="row.title"
                           :id="`subtask-title-${index}`" :name="`subtasks[${index}][title]`"
                           placeholder="{{ __('اكتب خطوة من العمل…') }}" maxlength="255">
                </div>

                {{-- One person on the ticket: the owner is not a question. --}}
                <template x-if="assignedRoleIds().length === 1">
                    <input type="hidden" :name="`subtasks[${index}][role_id]`" :value="assignedRoleIds()[0]">
                </template>

                <template x-if="assignedRoleIds().length > 1">
                    <div class="repeater__field">
                        <label class="label" :for="`subtask-owner-${index}`">{{ __('صاحبها') }}</label>
                        <select class="select" x-model="row.role_id" required
                                :id="`subtask-owner-${index}`" :name="`subtasks[${index}][role_id]`">
                            <option value="">{{ __('— اختر —') }}</option>
                            <template x-for="roleId in assignedRoleIds()" :key="roleId">
                                <option :value="roleId" x-text="roleNames[roleId]"></option>
                            </template>
                        </select>
                    </div>
                </template>

                {{-- ★ (2026-08-05) تاريخ الاستحقاق ليه صلاحية مستقلة
                     (subtasks.schedule) — بقى بيحدد النقط موجبة ولا سالبة.
                     الحقل مش بيتعرض أصلاً لغير المصرح، والكونترولر بيسقّطه
                     برضه لو اتبعت بالإيد. --}}
                @can('schedule', \App\Models\TicketSubtask::class)
                    <div class="repeater__field">
                        <label class="label" :for="`subtask-due-${index}`">{{ __('الاستحقاق') }}</label>
                        <input type="date" class="input" x-model="row.due_date"
                               :id="`subtask-due-${index}`" :name="`subtasks[${index}][due_date]`">
                    </div>
                @endcan

                <button type="button" class="repeater__remove" @click="subtaskRows.splice(index, 1)"
                        aria-label="{{ __('إزالة الصف') }}">
                    <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M5 5l10 10M15 5L5 15" stroke="currentColor" stroke-width="2" fill="none" />
                    </svg>
                </button>
            </div>
        </template>

        @error('subtasks')
            <p class="field__error">{{ $message }}</p>
        @enderror
        @foreach ($errors->get('subtasks.*.title') as $message)
            <p class="field__error">{{ $message[0] }}</p>
        @endforeach
        @foreach ($errors->get('subtasks.*.role_id') as $message)
            <p class="field__error">{{ $message[0] }}</p>
        @endforeach

        <x-button variant="ghost" size="sm" type="button" @click="add()"
                  x-bind:disabled="assignedRoleIds().length === 0">{{ __('+ إضافة مهمة فرعية') }}</x-button>
    </div>
</x-card>
