@php
    $advancedKeys = ['statuses', 'culprit', 'branch', 'label', 'module', 'origin', 'approval', 'creator',
        'created_by', 'resolved_by', 'closed_by', 'subtasks', 'has_subtasks', 'unassigned', 'reopened',
        'has_comments', 'has_attachments', 'late', 'deadline', 'sort'];
    $activeAdvanced = array_filter(array_intersect_key($filters, array_flip($advancedKeys)),
        fn ($value) => is_array($value) ? array_filter($value) !== [] : filled($value));
@endphp

<x-collapsible-section :title="__('فلاتر متقدمة')" :meta="trans_choice(':count فلتر شغّال', count($activeAdvanced))"
                       :open="$activeAdvanced !== []" class="filters__advanced-section">
    <div class="filters__advanced">
        <div class="filters__multi" x-data="{ open: false, selected: @js(array_values(array_filter((array) ($filters['statuses'] ?? [])))) }"
             @click.outside="open = false">
            <button type="button" class="select filters__multi-toggle" @click="open = !open"
                    :aria-expanded="open.toString()" aria-haspopup="listbox">
                <span x-text="selected.length ? {{ Js::from(__('الحالات:')) }} + ' ' + selected.length : {{ Js::from(__('الحالات: الكل')) }}">{{ __('الحالات: الكل') }}</span>
                <x-icon name="chevron-down" size="0.8em" />
            </button>
            <div class="filters__multi-menu" x-show="open" x-cloak role="listbox" aria-label="{{ __('حالات متعددة') }}">
                @foreach (\App\Models\TicketStatusDefinition::options() as $value => $label)
                    <label class="filters__multi-option">
                        <input type="checkbox" name="statuses[]" value="{{ $value }}" x-model="selected">
                        <span>{{ __($label) }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="filters__combobox"><x-combobox name="culprit" resource="users" :value="$filters['culprit'] ?? null" :selected="$selectedCulprit" :placeholder="__('متسبب الاكسبشن')" /></div>
        <select name="branch" class="select" aria-label="{{ __('البرانش') }}"><option value="">{{ __('البرانش: الكل') }}</option><option value="none" @selected(($filters['branch'] ?? '') === 'none')>{{ __('من غير برانش') }}</option><option value="has" @selected(($filters['branch'] ?? '') === 'has')>{{ __('ليها برانش') }}</option></select>
        <select name="label" class="select" aria-label="{{ __('اللابل') }}"><option value="">{{ __('أي لابل') }}</option>@foreach ($labels as $label)<option value="{{ $label->id }}" @selected((int) ($filters['label'] ?? 0) === $label->id)>{{ $label->label() }}</option>@endforeach</select>
        <input type="search" name="module" value="{{ $filters['module'] ?? '' }}" class="input" placeholder="{{ __('الموديول') }}" aria-label="{{ __('الموديول') }}">
        <select name="origin" class="select" aria-label="{{ __('مصدر التذكرة') }}"><option value="">{{ __('المصدر: الكل') }}</option>@foreach (\App\Models\Ticket::ORIGINS as $value => $label)<option value="{{ $value }}" @selected(($filters['origin'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
        <select name="approval" class="select" aria-label="{{ __('الموافقة') }}"><option value="">{{ __('الموافقة: الكل') }}</option>@foreach (\App\Models\Ticket::APPROVALS as $value => $label)<option value="{{ $value }}" @selected(($filters['approval'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
        <div class="filters__combobox"><x-combobox name="creator" resource="users" :value="$filters['creator'] ?? null" :selected="$selectedCreator" :placeholder="__('فتحها: أي حد')" /></div>
        <div class="filters__combobox"><x-combobox name="created_by" resource="users" :value="$filters['created_by'] ?? null" :selected="$selectedCreatedBy" :placeholder="__('أنشأها: أي حد')" /></div>
        <div class="filters__combobox"><x-combobox name="resolved_by" resource="users" :value="$filters['resolved_by'] ?? null" :selected="$selectedResolvedBy" :placeholder="__('حلّها: أي حد')" /></div>
        <div class="filters__combobox"><x-combobox name="closed_by" resource="users" :value="$filters['closed_by'] ?? null" :selected="$selectedClosedBy" :placeholder="__('قفلها: أي حد')" /></div>
        <select name="subtasks" class="select" aria-label="{{ __('حالة الصب تاسكس') }}"><option value="">{{ __('الصب تاسكس: الكل') }}</option>@foreach (\App\Models\Ticket::SUBTASK_STATES as $value => $label)<option value="{{ $value }}" @selected(($filters['subtasks'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
        <select name="has_subtasks" class="select" aria-label="{{ __('وجود صب تاسكس') }}"><option value="">{{ __('وجود صب تاسكس: الكل') }}</option><option value="yes" @selected(($filters['has_subtasks'] ?? '') === 'yes')>{{ __('فيها صب تاسكس') }}</option><option value="no" @selected(($filters['has_subtasks'] ?? '') === 'no')>{{ __('من غير صب تاسكس') }}</option></select>
        <select name="unassigned" class="select" aria-label="{{ __('الإسناد') }}"><option value="">{{ __('الإسناد: الكل') }}</option><option value="yes" @selected(($filters['unassigned'] ?? '') === 'yes')>{{ __('غير مسندة') }}</option><option value="no" @selected(($filters['unassigned'] ?? '') === 'no')>{{ __('مسندة') }}</option></select>
        <select name="reopened" class="select" aria-label="{{ __('إعادة الفتح') }}"><option value="">{{ __('إعادة الفتح: الكل') }}</option><option value="yes" @selected(($filters['reopened'] ?? '') === 'yes')>{{ __('اتفتحت تاني') }}</option><option value="no" @selected(($filters['reopened'] ?? '') === 'no')>{{ __('ما اتفتحتش تاني') }}</option></select>
        <select name="has_comments" class="select" aria-label="{{ __('التعليقات') }}"><option value="">{{ __('التعليقات: الكل') }}</option><option value="yes" @selected(($filters['has_comments'] ?? '') === 'yes')>{{ __('فيها تعليقات') }}</option><option value="no" @selected(($filters['has_comments'] ?? '') === 'no')>{{ __('من غير تعليقات') }}</option></select>
        <select name="has_attachments" class="select" aria-label="{{ __('المرفقات') }}"><option value="">{{ __('المرفقات: الكل') }}</option><option value="yes" @selected(($filters['has_attachments'] ?? '') === 'yes')>{{ __('فيها مرفقات') }}</option><option value="no" @selected(($filters['has_attachments'] ?? '') === 'no')>{{ __('من غير مرفقات') }}</option></select>
        <select name="late" class="select" aria-label="{{ __('التأخير') }}"><option value="">{{ __('التأخير: الكل') }}</option>@foreach (\App\Models\Ticket::LATENESS as $value => $label)<option value="{{ $value }}" @selected(($filters['late'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
        <select name="deadline" class="select" aria-label="{{ __('حالة الموعد النهائي') }}"><option value="">{{ __('الموعد النهائي: الكل') }}</option>@foreach (\App\Models\Ticket::DEADLINE_FILTERS as $value => $label)<option value="{{ $value }}" @selected(($filters['deadline'] ?? '') === $value)>{{ __($label) }}</option>@endforeach</select>
        <select name="sort" class="select" aria-label="{{ __('الترتيب') }}">@foreach (\App\Models\Ticket::SORTS as $value => $label)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'default') === $value)>{{ __('الترتيب: :sort', ['sort' => __($label)]) }}</option>@endforeach</select>
    </div>
</x-collapsible-section>
