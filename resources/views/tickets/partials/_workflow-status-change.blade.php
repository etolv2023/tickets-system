{{-- F06: the manual status change, with an optional "waiting on" recipient. --}}
@can('changeStatus', $ticket)
    @if ($nextStatuses->isNotEmpty())
        <x-collapsible-card title="غيّر الحالة">
            <form method="POST" action="{{ route('tickets.status.change', $ticket) }}"
                  class="form-stack" x-data="{ recipient: '' }">
                @csrf

                @error('status')
                    <p class="field__error">{{ $message }}</p>
                @enderror

                <x-field name="to_status" label="الحالة الجديدة" required>
                    <select id="to_status" name="to_status" class="select" required>
                        <option value="">— اختار —</option>
                        @foreach ($nextStatuses as $s)
                            <option value="{{ $s->key }}" @selected(old('to_status') === $s->key)>{{ $s->name_ar }}</option>
                        @endforeach
                    </select>
                </x-field>

                <x-field name="recipient_type" label="مستنيين رد مين؟" hint="اختياري — اختيار مستلم بيعمل صب تاسك متابعة تلقائي للمسؤول عن التذكرة.">
                    <select id="recipient_type" name="recipient_type" class="select" x-model="recipient">
                        <option value="">من غير مستلم</option>
                        <option value="team">حد من التيم</option>
                        <option value="company">حد من الشركة</option>
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

                <x-button variant="primary" size="sm">غيّر الحالة</x-button>
            </form>
        </x-collapsible-card>
    @endif
@endcan
