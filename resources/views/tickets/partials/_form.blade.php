@php $ticket = $ticket ?? null; @endphp

{{-- On create the origin toggle decides which fields are obligatory, and it is
     read by two cards that are not siblings — the reporter block and the access
     block. So the state sits here, around both. On edit there is no toggle: the
     ticket already knows what it is, and the answer comes from php. --}}
<div class="form-stack"
     @if (! $ticket) x-data="{ internal: @js((bool) old('is_internal', false)) }" @endif>
    <x-form-errors />

    <div class="ticket-form">
    <div class="ticket-form__col">
    @if ($ticket)
        @include('tickets.partials._snapshot')
    @else
        <x-card :title="__('المُبلغ')">
            @include('tickets.partials._reporter')
        </x-card>
    @endif

    <x-card :title="__('المشكلة')">
        <div class="form-stack">
            <x-field name="title" :label="__('العنوان')" :value="$ticket?->title" required
                     :placeholder="__('وصف قصير للمشكلة في سطر واحد')" />

            <div class="field">
                <label class="field__label" for="description">{{ __('الوصف') }}</label>
                <x-editor name="description" :value="$ticket?->description"
                          :placeholder="__('اشرح المشكلة بالتفصيل. يمكنك لصق الصور داخل المحرر.')" />
            </div>
        </div>
    </x-card>

    @include('tickets.partials._access')

    </div>

    <div class="ticket-form__col">
    <x-card :title="__('التصنيف')">
        <div class="form-grid">
            {{-- ★★★★★ (2026-07-21): type is a deliberate choice, not a silent
                 «غير محدد» default. A disabled empty placeholder + required
                 means the form can't submit until a real one is picked — which
                 is what stopped features being created as «غير محدد» (so they
                 skipped approval). On an edit the ticket's own value is
                 pre-selected, so nothing changes there. --}}
            <x-field name="type" :label="__('النوع')" required
                     :hint="__('تنتظر الميزات والوحدات الجديدة موافقة المسؤول قبل الإسناد.')">
                <select id="type" name="type" required @class(['select', 'select--invalid' => $errors->has('type')])>
                    <option value="" disabled @selected(blank(old('type', $ticket?->type?->value)))>{{ __('— اختر النوع —') }}</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $ticket?->type?->value) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="priority" :label="__('الأولوية')" required :hint="__('تحدد مهلة SLA تلقائيًا.')">
                <select id="priority" name="priority" required @class(['select', 'select--invalid' => $errors->has('priority')])>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->key }}"
                                @selected(old('priority', $ticket?->priority?->value ?? 'medium') === $priority->key)>
                            {{ $priority->label() }} — {{ trans_choice(':count ساعة|ساعة واحدة|ساعتان|:count ساعات|:count ساعة|:count ساعة', $priority->sla_hours) }}
                        </option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="module" :label="__('الوحدة')" :value="$ticket?->module"
                     :placeholder="__('الفواتير، المخزون…')" />

            <x-field name="due_date" :label="__('تاريخ التسليم')" type="date"
                     :value="$ticket?->due_date?->toDateString()" />
        </div>
    </x-card>

    @unless ($ticket)
        @isset($assignable)
            {{-- One Alpine scope around both blocks: the subtask repeater has to
                 react to the distribution above it, because a row's owner can
                 only be someone this ticket is actually being assigned to.

                 `assignments` is derived from the rows rather than stored, so the
                 repeater can't drift out of sync with what was actually picked —
                 there is one source of truth and it is the rows. --}}
            <div x-data="{
                     roles: @js($assignable['roles']->map(fn ($e) => [
                         'id' => $e['role']->id,
                         'name' => $e['role']->label(),
                         'candidates' => $e['candidates']->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                     ])->values()),
                     rows: Object.entries(@js((object) old('role_assignments', [])))
                         .filter(([, userId]) => userId)
                         .map(([roleId, userId]) => ({ roleId: String(roleId), userId: String(userId) })),

                     /* Roles still free, plus the one this row already holds. */
                     rolesFor(currentId) {
                         const taken = this.rows.map((r) => r.roleId).filter((id) => id && id !== currentId);
                         return this.roles.filter((role) => ! taken.includes(String(role.id)));
                     },
                     candidatesFor(roleId) {
                         return this.roles.find((role) => String(role.id) === String(roleId))?.candidates ?? [];
                     },

                     get assignments() {
                         return Object.fromEntries(
                             this.rows.filter((r) => r.roleId && r.userId).map((r) => [r.roleId, r.userId])
                         );
                     },
                     get roleNames() {
                         return Object.fromEntries(this.roles.map((role) => [role.id, role.name]));
                     },
                     assignedRoleIds() {
                         return Object.keys(this.assignments);
                     },
                 }">
                @include('tickets.partials._assign-fields')
                @include('tickets.partials._subtask-repeater')
            </div>
        @else
            {{-- No tickets.assign means no distribution on this page, and a
                 subtask with nobody on it earns nothing. The plan waits for the
                 ticket page, after someone distributes it. --}}
            <x-card :title="__('المهام الفرعية')">
                <p class="field__hint">
                    {{ __('تُضاف المهام الفرعية من صفحة التذكرة بعد إسنادها — يجب أن يكون لكل مهمة فرعية صاحب ليحصل على نقاطها.') }}
                </p>
            </x-card>
        @endisset
    @endunless

    {{-- ★ (2026-08-04) Outside the @unless: the edit form used to have no
         uploader at all, so pasting a picture into the description while
         editing hit "مفيش مرفقات في الصفحة دي" and did nothing. The editor
         hands its images to whichever uploader shares its <form> (editor.js →
         uploader.js), so no uploader on the page means no inline images on the
         page. --}}
    <x-card :title="__('المرفقات')">
        <x-uploader />
    </x-card>
    </div>
    </div>

    <div class="form-actions form-actions--sticky">
        <x-button variant="primary">{{ $ticket ? __('حفظ التعديلات') : __('افتح التذكرة') }}</x-button>
        <x-button variant="ghost" :href="$ticket ? route('tickets.show', $ticket) : route('tickets.index')">{{ __('إلغاء') }}</x-button>
    </div>
</div>
