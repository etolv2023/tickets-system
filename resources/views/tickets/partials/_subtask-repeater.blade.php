{{-- F08 — planning the work while opening the ticket, instead of opening it and
     then going to find the subtasks tab.

     Three fields per row on purpose. The full subtask form has eight (points,
     hours, blocked reason…), and most of them are meaningless for work that
     has not started: a subtask created with its ticket has no spent hours and
     is not blocked.

     ★ (2026-08-02) The "الرول" select became "صاحبها", and it is no longer
     decorative: store() turns it into the row's assignee_id. A subtask with no
     assignee is invisible to the point engine forever — TK-2026-00169 was
     resolved with seven of them and paid nobody. So the options are exactly the
     people being assigned in the block above, and:

       - nobody assigned yet  → no rows can be added at all
       - exactly one role     → it is the owner, no question asked
       - more than one        → the row must say which, every time

     Leaving the whole thing empty is still fine: blank rows are dropped in
     StoreTicketRequest before the rules run. --}}
<x-card title="الصب تاسكس (اختياري)">
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
            قسّم الشغل من دلوقتي لو عايز. تقدر تضيفها أو تعدّلها بعدين من صفحة التذكرة،
            وكل صب تاسك بتاخد نقط النوع افتراضياً وتتعدّل لوحدها.
        </p>

        <template x-if="assignedRoleIds().length === 0">
            <p class="field__hint">وزّع الشغل فوق الأول — الصب تاسك لازم يكون ليها صاحب عشان تكسب نقطها.</p>
        </template>

        <template x-for="(row, index) in subtaskRows" :key="index">
            <div class="repeater__row">
                <div class="repeater__field repeater__field--grow">
                    <label class="label" :for="`subtask-title-${index}`">العنوان</label>
                    <input type="text" class="input" x-model="row.title"
                           :id="`subtask-title-${index}`" :name="`subtasks[${index}][title]`"
                           placeholder="اكتب خطوة من الشغل…" maxlength="255">
                </div>

                {{-- One person on the ticket: the owner is not a question. --}}
                <template x-if="assignedRoleIds().length === 1">
                    <input type="hidden" :name="`subtasks[${index}][role_id]`" :value="assignedRoleIds()[0]">
                </template>

                <template x-if="assignedRoleIds().length > 1">
                    <div class="repeater__field">
                        <label class="label" :for="`subtask-owner-${index}`">صاحبها</label>
                        <select class="select" x-model="row.role_id" required
                                :id="`subtask-owner-${index}`" :name="`subtasks[${index}][role_id]`">
                            <option value="">— اختار —</option>
                            <template x-for="roleId in assignedRoleIds()" :key="roleId">
                                <option :value="roleId" x-text="roleNames[roleId]"></option>
                            </template>
                        </select>
                    </div>
                </template>

                <div class="repeater__field">
                    <label class="label" :for="`subtask-due-${index}`">الاستحقاق</label>
                    <input type="date" class="input" x-model="row.due_date"
                           :id="`subtask-due-${index}`" :name="`subtasks[${index}][due_date]`">
                </div>

                <button type="button" class="repeater__remove" @click="subtaskRows.splice(index, 1)"
                        aria-label="شيل الصف">
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
                  x-bind:disabled="assignedRoleIds().length === 0">+ أضف صب تاسك</x-button>
    </div>
</x-card>
