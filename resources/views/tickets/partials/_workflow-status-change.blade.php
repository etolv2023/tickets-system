{{-- F06: the manual status change, with an optional "waiting on" recipient. --}}
@can('changeStatus', $ticket)
    @if ($nextStatuses->isNotEmpty())
        {{-- Open by default: the single most-used control on this screen. --}}
        <x-collapsible-card title="تحديث مرحلة التذكرة" name="status-change" :open="true">
            <p class="field__hint">اختر المرحلة التي تصف وضع الالتزام الآن. لا تستخدم «تم الحل» لمجرد انتهاء البرمجة؛ يجب اعتماد النتيجة أولًا.</p>
            <form method="POST" action="{{ route('tickets.status.change', $ticket) }}"
                  class="form-stack" x-data="{ recipient: '' }">
                @csrf

                @error('status')
                    <p class="field__error">{{ $message }}</p>
                @enderror

                <x-field name="to_status" label="الحالة الجديدة" required>
                    <select id="to_status" name="to_status" class="select" required>
                        <option value="">— اختر المرحلة —</option>
                        @foreach ($nextStatuses as $s)
                            <option value="{{ $s->key }}" @selected(old('to_status') === $s->key)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </x-field>

                @foreach ($nextStatuses as $s)
                    @if ($s->description())
                        <p class="field__hint"><strong>{{ $s->label() }}:</strong> {{ $s->description() }}</p>
                    @endif
                @endforeach

                <x-field name="recipient_type" label="الطرف المنتظر منه رد" hint="اختياري — ينشئ النظام مهمة متابعة تلقائية لمسؤول التذكرة.">
                    <select id="recipient_type" name="recipient_type" class="select" x-model="recipient">
                        <option value="">لا يوجد طرف منتظر</option>
                        <option value="team">عضو من الفريق</option>
                        <option value="company">جهة اتصال لدى العميل</option>
                    </select>
                </x-field>

                <div x-show="recipient === 'team'" x-cloak>
                    <x-field name="recipient_user_id" label="الشخص من التيم">
                        <select id="recipient_user_id" name="recipient_user_id" class="select">
                            <option value="">— اختار —</option>
                            @foreach ($recipientTeam as $person)
                                <option value="{{ $person->id }}" @selected((int) old('recipient_user_id') === $person->id)>
                                    {{ $person->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <div x-show="recipient === 'company'" x-cloak>
                    <x-field name="recipient_contact_id" label="الشخص من الشركة">
                        <select id="recipient_contact_id" name="recipient_contact_id" class="select">
                            <option value="">— اختار —</option>
                            @foreach ($recipientContacts as $contact)
                                <option value="{{ $contact->id }}" @selected((int) old('recipient_contact_id') === $contact->id)>
                                    {{ $contact->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-field>
                </div>

                <x-field name="note" label="ملاحظة">
                    <textarea id="note" name="note" class="textarea" rows="2">{{ old('note') }}</textarea>
                </x-field>

                <x-button variant="primary" size="sm">حفظ المرحلة الجديدة</x-button>
            </form>
        </x-collapsible-card>
    @endif
@endcan
