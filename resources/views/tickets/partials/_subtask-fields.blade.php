@php
    $subtask = $subtask ?? null;
    $prefix = $subtask ? "st{$subtask->id}-" : 'new-';
@endphp

{{-- x-data carries the status so the blocked-reason field can appear the moment
     it becomes required, rather than after a failed submit. F08 --}}
<div class="form-stack" x-data="{
        status: @js(old('status', $subtask?->status?->value ?? 'todo')),
        reasonStatuses: @js(\App\Models\SubtaskStatusDefinition::reasonKeys()),
    }">
    <x-field :name="'title'" label="العنوان" :value="$subtask?->title" required :id="$prefix . 'title'" />

    <div class="form-grid">
        @php
            // ★ (2026-08-02) Moving a subtask to someone else is its own
            // permission — it moves who gets paid for it. A new subtask is never
            // a "move", so the picker always shows while creating.
            $mayReassign = $subtask === null
                || auth()->user()->can('reassign', [$subtask, $ticket]);
        @endphp

        @if ($mayReassign)
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
        @else
            {{-- Shown, not hidden: the owner is a fact about the subtask you are
                 editing, and a field that vanishes reads as a bug. Locked, with
                 the reason, is the honest version. --}}
            <x-field name="assignee_locked" label="المسؤول" :id="$prefix . 'assignee-locked'"
                     hint="نقل الصب تاسك لشخص تاني محتاج صلاحية.">
                <input id="{{ $prefix }}assignee-locked" class="input input--locked" type="text" readonly
                       value="{{ $subtask->assignee?->name ?? 'مش مسندة' }}">
            </x-field>
        @endif

        {{-- The role picker is gone (2026-07-23): a subtask's role follows its
             owner and is set server-side (SubtaskService), so it can't drift
             from who's actually doing the work. No field here — the assignee
             above is the single input that decides both. --}}

        <x-field name="status" label="الحالة" :id="$prefix . 'status'">
            <select id="{{ $prefix }}status" name="status" class="select" required x-model="status">
                @foreach (\App\Models\SubtaskStatusDefinition::options() as $value => $label)
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
                     hint="{{ $subtask ? '' : 'سيبها فاضية لتاخد النقطة الواحدة الافتراضية.' }}" />
        @else
            {{-- النقاط ليها صلاحية مستقلة (points.rules.manage) — هو الوحيد اللي
                 يقدر يعدّلها، قراءة بس هنا، نفس نمط input--locked المستخدم
                 للحقول اللي النظام بيملاها مش اليوزر. --}}
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
        @can('schedule', \App\Models\TicketSubtask::class)
            <x-field name="due_date" label="تاريخ الاستحقاق" type="date"
                     :value="$subtask?->due_date?->toDateString()" :id="$prefix . 'due'"
                     hint="لو حطيت تاريخ وخلصت بعده، النقط بتتحسب بالسالب. سيبه فاضي لو مفيش ديدلاين." />
        @else
            {{-- التاريخ ليه صلاحية مستقلة (subtasks.schedule) من يوم ما بقى
                 بيحدد النقط موجبة ولا سالبة — اللي شغال على التاسك مينفعش
                 يحرّك الديدلاين اللي بيتحاسب بيه. قراءة بس، نفس نمط النقاط. --}}
            <div class="field">
                <label class="field__label" for="{{ $prefix }}due">تاريخ الاستحقاق</label>
                <input type="text" id="{{ $prefix }}due" class="input input--locked" disabled
                       value="{{ $subtask?->due_date?->translatedFormat('j M Y') ?? 'مفيش تاريخ' }}">
                <p class="field__hint">التاريخ بيتحدد من مدير التيم — وهو اللي بيتحاسب عليه النقط.</p>
            </div>
        @endcan
    </div>

    <div x-show="reasonStatuses.includes(status)" x-cloak>
        <x-field name="blocked_reason" label="سبب التوقف" :value="$subtask?->blocked_reason"
                 :id="$prefix . 'reason'" hint="إجباري لما الحالة تكون متوقفة." />
    </div>

    <x-field name="description" label="وصف مختصر" :id="$prefix . 'desc'">
        <textarea id="{{ $prefix }}desc" name="description" class="textarea" rows="2">{{ old('description', $subtask?->description) }}</textarea>
    </x-field>
</div>
