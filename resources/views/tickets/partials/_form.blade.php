@php $ticket = $ticket ?? null; @endphp

<div class="form-stack">
    <x-form-errors />

    <div class="ticket-form">
    <div class="ticket-form__col">
    @if ($ticket)
        {{-- Company, contact and the reported time are the snapshot of who said
             what and when. Rendered as locked fields rather than a facts list:
             they sit in the same column as the editable ones, so the form reads
             as one shape and the lock is the only difference. F03 --}}
        <x-card title="المُبلغ">
            <x-slot:actions>
                <span class="field__snapshot">
                    <x-icon name="lock" size="0.9em" />
                    لقطة ثابتة
                </span>
            </x-slot:actions>

            <div class="form-stack">
                <div class="form-grid">
                    <div class="field">
                        <label class="field__label" for="locked-company">
                            {{ $ticket->isInternal() ? 'طلبها' : 'الشركة' }}
                        </label>
                        <input id="locked-company" class="input input--locked" type="text"
                               value="{{ $ticket->originLabel() }}" readonly>
                    </div>

                    @unless ($ticket->isInternal())
                        <div class="field">
                            <label class="field__label" for="locked-reporter">المُبلغ</label>
                            <input id="locked-reporter" class="input input--locked" type="text"
                                   value="{{ $ticket->reporter_name }}{{ $ticket->reporter_erp_id ? " ({$ticket->reporter_erp_id})" : '' }}"
                                   readonly>
                        </div>
                    @endunless
                </div>

                <div class="field">
                    <label class="field__label" for="locked-reported-at">وقت الإبلاغ</label>
                    <input id="locked-reported-at" class="input input--locked u-mono u-ltr" type="text"
                           value="{{ $ticket->reported_at->timezone(config('app.display_timezone'))->translatedFormat('j F Y — H:i') }}"
                           readonly>
                </div>

                <p class="field__hint">الحقول دي بتتسجّل مرة واحدة وقت فتح التذكرة ومبتتغيّرش.</p>
            </div>
        </x-card>
    @else
        <x-card title="المُبلغ">
            @include('tickets.partials._reporter')
        </x-card>
    @endif

    <x-card title="المشكلة">
        <div class="form-stack">
            <x-field name="title" label="العنوان" :value="$ticket?->title" required
                     placeholder="وصف قصير للمشكلة في سطر" />

            <div class="field">
                <label class="field__label" for="description">الوصف</label>
                <x-editor name="description" :value="$ticket?->description"
                          placeholder="اشرح المشكلة بالتفصيل. تقدر تلصق صور جوه المحرر." />
            </div>
        </div>
    </x-card>
    </div>

    <div class="ticket-form__col">
    <x-card title="التصنيف">
        <div class="form-grid">
            {{-- ★★★★★ (2026-07-21): type is a deliberate choice, not a silent
                 «غير محدد» default. A disabled empty placeholder + required
                 means the form can't submit until a real one is picked — which
                 is what stopped features being created as «غير محدد» (so they
                 skipped approval). On an edit the ticket's own value is
                 pre-selected, so nothing changes there. --}}
            <x-field name="type" label="النوع" required
                     hint="الفيتشر والموديول الجديد بيستنوا موافقة الأدمن قبل التوزيع.">
                <select id="type" name="type" required @class(['select', 'select--invalid' => $errors->has('type')])>
                    <option value="" disabled @selected(blank(old('type', $ticket?->type?->value)))>— اختر النوع —</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $ticket?->type?->value) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="priority" label="الأولوية" required hint="بتحدد مهلة الـ SLA أوتوماتيك.">
                <select id="priority" name="priority" required @class(['select', 'select--invalid' => $errors->has('priority')])>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->key }}"
                                @selected(old('priority', $ticket?->priority?->value ?? 'medium') === $priority->key)>
                            {{ $priority->name_ar }} — {{ $priority->sla_hours }} ساعة
                        </option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="module" label="الموديول" :value="$ticket?->module"
                     placeholder="الفواتير، المخزون…" />
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
                         'name' => $e['role']->name_ar,
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
            <x-card title="الصب تاسكس">
                <p class="field__hint">
                    الصب تاسكس بتتضاف من صفحة التذكرة بعد ما تتوزّع — كل صب تاسك لازم يكون ليها صاحب عشان تكسب نقطها.
                </p>
            </x-card>
        @endisset

        <x-card title="المرفقات">
            <x-uploader />
        </x-card>
    @endunless
    </div>
    </div>

    <div class="form-actions form-actions--sticky">
        <x-button variant="primary">{{ $ticket ? 'حفظ التعديلات' : 'افتح التذكرة' }}</x-button>
        <x-button variant="ghost" :href="$ticket ? route('tickets.show', $ticket) : route('tickets.index')">إلغاء</x-button>
    </div>
</div>
