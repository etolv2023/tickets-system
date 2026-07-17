@php $ticket = $ticket ?? null; @endphp

<div class="form-stack">
    <x-form-errors />

    @if ($ticket)
        {{-- Company, contact and the reported time are the snapshot of who said
             what and when. They are shown, never edited. F03 --}}
        <x-card title="المُبلغ">
            <div class="facts">
                <div class="facts__row">
                    <span class="facts__label">الشركة</span>
                    <span class="facts__value">{{ $ticket->company->name }}</span>
                </div>
                <div class="facts__row">
                    <span class="facts__label">المُبلغ</span>
                    <span class="facts__value">
                        {{ $ticket->reporter_name }}
                        @if ($ticket->reporter_erp_id)
                            <span class="u-mono u-subtle">({{ $ticket->reporter_erp_id }})</span>
                        @endif
                    </span>
                </div>
                <div class="facts__row">
                    <span class="facts__label">وقت الإبلاغ</span>
                    <span class="facts__value facts__value--num">
                        {{ $ticket->reported_at->timezone(config('app.display_timezone'))->translatedFormat('j F Y — H:i') }}
                    </span>
                </div>
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
                        <option value="{{ $priority->value }}"
                                @selected(old('priority', $ticket?->priority?->value ?? 'medium') === $priority->value)>
                            {{ $priority->label() }} — {{ $priority->defaultSlaHours() }} ساعة
                        </option>
                    @endforeach
                </select>
            </x-field>

            <x-field name="module" label="الموديول" :value="$ticket?->module"
                     placeholder="الفواتير، المخزون…" />
        </div>
    </x-card>

    @unless ($ticket)
        <x-card title="المرفقات">
            <x-uploader :max="\App\Services\AttachmentService::MAX_PER_TICKET" />
        </x-card>
    @endunless

    <div class="form-actions">
        <x-button variant="primary">{{ $ticket ? 'حفظ التعديلات' : 'افتح التذكرة' }}</x-button>
        <x-button variant="ghost" :href="$ticket ? route('tickets.show', $ticket) : route('tickets.index')">إلغاء</x-button>
    </div>
</div>
