{{-- F08 — planning the work while opening the ticket, instead of opening it and
     then going to find the subtasks tab.

     Three fields per row on purpose. The full subtask form has eight (points,
     hours, blocked reason…), and most of them are meaningless for work that
     has not started: a subtask created with its ticket has no spent hours and
     is not blocked. Assignee is left out too — assignment happens in the block
     right above this one, and repeating it per row invites the two to disagree.

     Leaving this empty is exactly today's behaviour: blank rows are dropped in
     StoreTicketRequest before the rules run. --}}
<x-card title="الصب تاسكس (اختياري)">
    <div class="repeater"
         x-data="{
             rows: @js(old('subtasks', [])),
             add() { this.rows.push({ title: '', side: 'other', due_date: '' }) },
         }">
        <p class="repeater__lead">
            قسّم الشغل من دلوقتي لو عايز. تقدر تضيفها أو تعدّلها بعدين من صفحة التذكرة،
            وكل صب تاسك بتاخد نقاطها من مصفوفة النقاط لوحدها.
        </p>

        <template x-for="(row, index) in rows" :key="index">
            <div class="repeater__row">
                <div class="repeater__field repeater__field--grow">
                    <label class="label" :for="`subtask-title-${index}`">العنوان</label>
                    <input type="text" class="input" x-model="row.title"
                           :id="`subtask-title-${index}`" :name="`subtasks[${index}][title]`"
                           placeholder="اكتب خطوة من الشغل…" maxlength="255">
                </div>

                <div class="repeater__field">
                    <label class="label" :for="`subtask-side-${index}`">الجهة</label>
                    <select class="select" x-model="row.side"
                            :id="`subtask-side-${index}`" :name="`subtasks[${index}][side]`">
                        @foreach (\App\Enums\SubtaskSide::cases() as $side)
                            <option value="{{ $side->value }}">{{ $side->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="repeater__field">
                    <label class="label" :for="`subtask-due-${index}`">الاستحقاق</label>
                    <input type="date" class="input" x-model="row.due_date"
                           :id="`subtask-due-${index}`" :name="`subtasks[${index}][due_date]`">
                </div>

                <button type="button" class="repeater__remove" @click="rows.splice(index, 1)"
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

        <x-button variant="ghost" size="sm" type="button" @click="add()">+ أضف صب تاسك</x-button>
    </div>
</x-card>
