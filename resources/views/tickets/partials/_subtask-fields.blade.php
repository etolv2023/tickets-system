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
    </div>

    <div class="form-grid">
        <x-field name="start_date" label="تاريخ البداية" type="date"
                 :value="$subtask?->start_date?->toDateString()" :id="$prefix . 'start'" />
        <x-field name="due_date" label="تاريخ الاستحقاق" type="date"
                 :value="$subtask?->due_date?->toDateString()" :id="$prefix . 'due'" />
    </div>

    <div x-show="status === 'blocked'" x-cloak>
        <x-field name="blocked_reason" label="سبب التوقف" :value="$subtask?->blocked_reason"
                 :id="$prefix . 'reason'" hint="إجباري لما الحالة تكون متوقفة." />
    </div>

    <x-field name="description" label="وصف مختصر" :id="$prefix . 'desc'">
        <textarea id="{{ $prefix }}desc" name="description" class="textarea" rows="2">{{ old('description', $subtask?->description) }}</textarea>
    </x-field>
</div>
