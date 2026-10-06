@php
    $advancedKeys = ['statuses', 'culprit', 'branch', 'label', 'module', 'origin', 'approval', 'creator',
        'created_by', 'resolved_by', 'closed_by', 'subtasks', 'has_subtasks', 'unassigned', 'reopened',
        'has_comments', 'has_attachments', 'late', 'deadline', 'sort'];
    $activeAdvanced = array_filter(array_intersect_key($filters, array_flip($advancedKeys)),
        fn ($value) => is_array($value) ? array_filter($value) !== [] : filled($value));
@endphp

<x-collapsible-section title="فلاتر متقدمة" :meta="count($activeAdvanced) . ' فلتر شغّال'"
                       :open="$activeAdvanced !== []" class="filters__advanced-section">
    <div class="filters__advanced">
        <select name="statuses[]" class="select" aria-label="حالات متعددة" multiple>
            @foreach (\App\Models\TicketStatusDefinition::options() as $value => $label)
                <option value="{{ $value }}" @selected(in_array($value, (array) ($filters['statuses'] ?? []), true))>{{ $label }}</option>
            @endforeach
        </select>
        <div class="filters__combobox"><x-combobox name="culprit" resource="users" :value="$filters['culprit'] ?? null" :selected="$selectedCulprit" placeholder="متسبب الاكسبشن" /></div>
        <select name="branch" class="select" aria-label="البرانش"><option value="">البرانش: الكل</option><option value="none" @selected(($filters['branch'] ?? '') === 'none')>من غير برانش</option><option value="has" @selected(($filters['branch'] ?? '') === 'has')>ليها برانش</option></select>
        <select name="label" class="select" aria-label="اللابل"><option value="">أي لابل</option>@foreach ($labels as $label)<option value="{{ $label->id }}" @selected((int) ($filters['label'] ?? 0) === $label->id)>{{ $label->name }}</option>@endforeach</select>
        <input type="search" name="module" value="{{ $filters['module'] ?? '' }}" class="input" placeholder="الموديول" aria-label="الموديول">
        <select name="origin" class="select" aria-label="مصدر التذكرة"><option value="">المصدر: الكل</option>@foreach (\App\Models\Ticket::ORIGINS as $value => $label)<option value="{{ $value }}" @selected(($filters['origin'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="approval" class="select" aria-label="الموافقة"><option value="">الموافقة: الكل</option>@foreach (\App\Models\Ticket::APPROVALS as $value => $label)<option value="{{ $value }}" @selected(($filters['approval'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <div class="filters__combobox"><x-combobox name="creator" resource="users" :value="$filters['creator'] ?? null" :selected="$selectedCreator" placeholder="فتحها: أي حد" /></div>
        <div class="filters__combobox"><x-combobox name="created_by" resource="users" :value="$filters['created_by'] ?? null" :selected="$selectedCreatedBy" placeholder="أنشأها: أي حد" /></div>
        <div class="filters__combobox"><x-combobox name="resolved_by" resource="users" :value="$filters['resolved_by'] ?? null" :selected="$selectedResolvedBy" placeholder="حلّها: أي حد" /></div>
        <div class="filters__combobox"><x-combobox name="closed_by" resource="users" :value="$filters['closed_by'] ?? null" :selected="$selectedClosedBy" placeholder="قفلها: أي حد" /></div>
        <select name="subtasks" class="select" aria-label="حالة الصب تاسكس"><option value="">الصب تاسكس: الكل</option>@foreach (\App\Models\Ticket::SUBTASK_STATES as $value => $label)<option value="{{ $value }}" @selected(($filters['subtasks'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="has_subtasks" class="select" aria-label="وجود صب تاسكس"><option value="">وجود صب تاسكس: الكل</option><option value="yes" @selected(($filters['has_subtasks'] ?? '') === 'yes')>فيها صب تاسكس</option><option value="no" @selected(($filters['has_subtasks'] ?? '') === 'no')>من غير صب تاسكس</option></select>
        <select name="unassigned" class="select" aria-label="الإسناد"><option value="">الإسناد: الكل</option><option value="yes" @selected(($filters['unassigned'] ?? '') === 'yes')>غير مسندة</option><option value="no" @selected(($filters['unassigned'] ?? '') === 'no')>مسندة</option></select>
        <select name="reopened" class="select" aria-label="إعادة الفتح"><option value="">إعادة الفتح: الكل</option><option value="yes" @selected(($filters['reopened'] ?? '') === 'yes')>اتفتحت تاني</option><option value="no" @selected(($filters['reopened'] ?? '') === 'no')>ما اتفتحتش تاني</option></select>
        <select name="has_comments" class="select" aria-label="التعليقات"><option value="">التعليقات: الكل</option><option value="yes" @selected(($filters['has_comments'] ?? '') === 'yes')>فيها تعليقات</option><option value="no" @selected(($filters['has_comments'] ?? '') === 'no')>من غير تعليقات</option></select>
        <select name="has_attachments" class="select" aria-label="المرفقات"><option value="">المرفقات: الكل</option><option value="yes" @selected(($filters['has_attachments'] ?? '') === 'yes')>فيها مرفقات</option><option value="no" @selected(($filters['has_attachments'] ?? '') === 'no')>من غير مرفقات</option></select>
        <select name="late" class="select" aria-label="التأخير"><option value="">التأخير: الكل</option>@foreach (\App\Models\Ticket::LATENESS as $value => $label)<option value="{{ $value }}" @selected(($filters['late'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="deadline" class="select" aria-label="حالة الموعد النهائي"><option value="">الموعد النهائي: الكل</option>@foreach (\App\Models\Ticket::DEADLINE_FILTERS as $value => $label)<option value="{{ $value }}" @selected(($filters['deadline'] ?? '') === $value)>{{ $label }}</option>@endforeach</select>
        <select name="sort" class="select" aria-label="الترتيب">@foreach (\App\Models\Ticket::SORTS as $value => $label)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'default') === $value)>الترتيب: {{ $label }}</option>@endforeach</select>
    </div>
</x-collapsible-section>
