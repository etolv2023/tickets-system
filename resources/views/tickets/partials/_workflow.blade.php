@php
    $me = auth()->user();
    $myLogs = $ticket->workLogs->where('user_id', $me->id);
@endphp

{{-- F15: nothing happens on a feature until the admin decides. --}}
@can('approve', $ticket)
    <x-collapsible-card title="مستنية قرارك">
        <div class="stack stack--tight">
            <p class="field__hint">
                دي {{ $ticket->type->label() }} — متتوزعش قبل ما توافق.
            </p>

            <div class="row">
                <form method="POST" action="{{ route('tickets.approve', $ticket) }}">
                    @csrf
                    <x-button variant="primary" size="sm">موافقة</x-button>
                </form>

                <form method="POST" action="{{ route('tickets.reject', $ticket) }}" class="row">
                    @csrf
                    <input type="text" name="reason" class="input" placeholder="سبب الرفض (إجباري)" required>
                    <x-button variant="ghost" size="sm">رفض</x-button>
                </form>
            </div>
        </div>
    </x-collapsible-card>
@endcan

{{-- F07: the start / finish buttons, one per side this user owns. --}}
@if ($myLogs->isNotEmpty())
    <x-collapsible-card title="شغلي">
        <div class="stack stack--tight">
            @foreach ($myLogs as $log)
                <div class="row row--between">
                    <span>
                        {{ $log->side->label() }}
                        <x-badge :variant="$log->statusVariant()">{{ $log->statusLabel() }}</x-badge>
                    </span>

                    @can('manageWorkLog', $ticket)
                        @if ($log->status === 'pending')
                            <form method="POST" action="{{ route('tickets.work.start', [$ticket, $log]) }}">
                                @csrf
                                <x-button variant="primary" size="sm">بدأت</x-button>
                            </form>
                        @elseif ($log->status === 'in_progress')
                            <form method="POST" action="{{ route('tickets.work.finish', [$ticket, $log]) }}">
                                @csrf
                                <x-button variant="secondary" size="sm">خلصت</x-button>
                            </form>
                        @else
                            <span class="u-subtle">
                                {{ $log->finished_at?->diffForHumans() }}
                            </span>
                        @endif
                    @endcan
                </div>
            @endforeach
        </div>
    </x-collapsible-card>
@endif

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

{{-- F16: the tester's two buttons. --}}
@if ($ticket->tester_id === $me->id && in_array($ticket->status->value, ['dev_done', 'testing'], true))
    <x-collapsible-card title="التيست">
        <div class="stack stack--tight">
            <form method="POST" action="{{ route('tickets.verify', $ticket) }}">
                @csrf
                <x-button variant="primary" size="sm" block>تم التأكيد — اتحلت</x-button>
            </form>

            <form method="POST" action="{{ route('tickets.reopen', $ticket) }}" class="stack stack--tight">
                @csrf
                <input type="text" name="reason" class="input" placeholder="سبب الارتجاع (إجباري)" required>
                <x-button variant="ghost" size="sm" block>مرتجعة</x-button>
            </form>
        </div>
    </x-collapsible-card>
@endif

{{-- F06: telling the customer, then closing. The order is enforced. --}}
@canany(['notifyClient', 'close'])
    @if (in_array($ticket->status->value, ['resolved', 'closed'], true))
        <x-collapsible-card title="العميل">
            <div class="stack stack--tight">
                @if ($ticket->client_notified_at)
                    <p class="field__hint">
                        اتبلغ {{ $ticket->client_notified_at->diffForHumans() }}.
                    </p>
                @else
                    @can('notifyClient', $ticket)
                        <form method="POST" action="{{ route('tickets.notify', $ticket) }}">
                            @csrf
                            <x-button variant="secondary" size="sm" block>تم إبلاغ العميل</x-button>
                        </form>
                    @endcan
                @endif

                @can('close', $ticket)
                    @if ($ticket->status->value !== 'closed')
                        <form method="POST" action="{{ route('tickets.close', $ticket) }}">
                            @csrf
                            <x-button variant="primary" size="sm" block
                                      :disabled="$ticket->client_notified_at === null">
                                اقفل التذكرة
                            </x-button>
                        </form>

                        @unless ($ticket->client_notified_at)
                            <p class="field__hint">لازم تبلغ العميل الأول.</p>
                        @endunless
                    @endif
                @endcan
            </div>
        </x-collapsible-card>
    @endif
@endcanany
