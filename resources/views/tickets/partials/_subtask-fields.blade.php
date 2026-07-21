@php
    $subtask = $subtask ?? null;
    $prefix = $subtask ? "st{$subtask->id}-" : 'new-';
@endphp

{{-- x-data carries the status so the blocked-reason field can appear the moment
     it becomes required, rather than after a failed submit. F08 --}}
<div class="form-stack" x-data="{ status: @js(old('status', $subtask?->status?->value ?? 'todo')) }">
    <x-field :name="'title'" label="العنوان" :value="$subtask?->title" required :id="$prefix . 'title'" />

    <div class="form-grid">
        <x-field name="assignee_id" label="المسؤول" :id="$prefix . 'assignee'">
            <select id="{{ $prefix }}assignee" name="assignee_id" class="select">
                <option value="">— مش مسندة —</option>
                @foreach ($assignableAll as $person)
                    <option value="{{ $person->id }}" @selected((int) old('assignee_id', $subtask?->assignee_id) === $person->id)>
                        {{ $person->name }}
                    </option>
                @endforeach
            </select>
        </x-field>

        <x-field name="side" label="الجهة" :id="$prefix . 'side'"
                 hint="فرونت أو باك بس هما الي بيمنعوا «خلصت».">
            <select id="{{ $prefix }}side" name="side" class="select" required>
                @foreach (\App\Enums\SubtaskSide::options() as $value => $label)
                    <option value="{{ $value }}" @selected(old('side', $subtask?->side?->value ?? 'other') === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </x-field>

        <x-field name="status" label="الحالة" :id="$prefix . 'status'">
            <select id="{{ $prefix }}status" name="status" class="select" required x-model="status">
                @foreach (\App\Enums\SubtaskStatus::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="estimated_hours" label="التقدير (ساعات)" type="number"
                 :value="$subtask?->estimated_hours" step="0.25" min="0" max="999"
                 :id="$prefix . 'estimate'" />

        @can('updatePoints', \App\Models\TicketSubtask::class)
            <x-field name="points" label="النقاط" type="number"
                     :value="$subtask?->points" step="0.5" min="0" max="999"
                     :id="$prefix . 'points'"
                     hint="{{ $subtask ? '' : 'سيبها فاضية لتاخد قيمة مصفوفة النقاط الافتراضية.' }}" />
        @else
            {{-- النقاط بتتحدد من مصفوفة النقاط بس، والأدمن (points.rules.manage)
                 هو الوحيد اللي يقدر يعدّلها — قراءة بس هنا، نفس نمط
                 input--locked المستخدم للحقول اللي النظام بيملاها مش اليوزر. --}}
            <div class="field">
                <label class="field__label" for="{{ $prefix }}points">النقاط</label>
                <input type="text" id="{{ $prefix }}points" class="input input--locked" disabled
                       value="{{ $subtask ? rtrim(rtrim((string) $subtask->points, '0'), '.') . ' نقطة' : '—' }}">
            </div>
        @endcan
    </div>

    {{-- One date, not two. The estimate is in hours, so a start-to-due span of
         several days said something the hours contradicted — a 4-hour task
         sitting across 3 days. The subtask lands on the calendar on the day it
         is due, and the hours say how long it takes. --}}
    <div class="form-grid">
        <x-field name="due_date" label="تاريخ الاستحقاق" type="date"
                 :value="$subtask?->due_date?->toDateString()" :id="$prefix . 'due'"
                 hint="اليوم الي الصب تاسك مستحقة فيه — ده اللي بيظهر في الكاليندر." />
    </div>

    <div x-show="status === 'blocked'" x-cloak>
        <x-field name="blocked_reason" label="سبب التوقف" :value="$subtask?->blocked_reason"
                 :id="$prefix . 'reason'" hint="إجباري لما الحالة تكون متوقفة." />
    </div>

    <x-field name="description" label="وصف مختصر" :id="$prefix . 'desc'">
        <textarea id="{{ $prefix }}desc" name="description" class="textarea" rows="2">{{ old('description', $subtask?->description) }}</textarea>
    </x-field>
</div>
