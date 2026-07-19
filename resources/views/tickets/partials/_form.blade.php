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
            <x-field name="type" label="النوع" required
                     hint="الفيتشر والموديول الجديد بيستنوا موافقة الأدمن قبل التوزيع.">
                <select id="type" name="type" required @class(['select', 'select--invalid' => $errors->has('type')])>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $ticket?->type?->value ?? 'undefined') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="scope" label="النطاق" required>
                <select id="scope" name="scope" required @class(['select', 'select--invalid' => $errors->has('scope')])>
                    @foreach ($scopes as $value => $label)
                        <option value="{{ $value }}" @selected(old('scope', $ticket?->scope?->value ?? 'undefined') === $value)>
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
            @include('tickets.partials._assign-fields')
        @endisset

        @include('tickets.partials._subtask-repeater')

        <x-card title="المرفقات">
            <x-uploader :max="\App\Services\AttachmentService::MAX_PER_TICKET" />
        </x-card>
    @endunless
    </div>
    </div>

    <div class="form-actions form-actions--sticky">
        <x-button variant="primary">{{ $ticket ? 'حفظ التعديلات' : 'افتح التذكرة' }}</x-button>
        <x-button variant="ghost" :href="$ticket ? route('tickets.show', $ticket) : route('tickets.index')">إلغاء</x-button>
    </div>
</div>
