{{-- The corrected values, folded under the row they belong to.

     Every field the create form has, pre-filled with what the row currently
     says. A correction that went to the wrong person or under the wrong role is
     exactly the mistake this button exists to fix, so "cancel it and retype the
     whole thing" would be the same two rows with more chances to fat-finger.

     Field ids carry the row id: five of these can be open at once in principle,
     and two <label for="points"> on one page point at the same input. --}}

<form method="POST" action="{{ route('admin.point-rules.corrections.update', $correction) }}" class="form-stack">
    @csrf
    @method('PUT')

    <p class="field__hint">
        الحفظ هيكتب سطرين: واحد عكسي بيلغي اللي فوق، وواحد بالقيمة الجديدة —
        الاتنين على نفس شهر التصحيح الأصلي ({{ $correction->period }}).
    </p>

    <div class="form-grid">
        <x-field :name="'user_id_' . $correction->id" label="المستخدم" required>
            <select id="user_id_{{ $correction->id }}" name="user_id" class="select" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected($correction->user_id === $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field :name="'role_id_' . $correction->id" label="الدور" required>
            <select id="role_id_{{ $correction->id }}" name="role_id" class="select" required>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected($correction->role_id === $role->id)>{{ $role->name_ar }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field :name="'points_' . $correction->id" label="النقاط" required
                 hint="رقم موجب يضيف، رقم سالب يخصم.">
            <input type="number" step="0.5" id="points_{{ $correction->id }}" name="points" class="input"
                   value="{{ rtrim(rtrim($correction->points, '0'), '.') }}" required>
        </x-field>

        <x-field :name="'ticket_number_' . $correction->id" label="رقم التذكرة (اختياري)">
            <input type="text" id="ticket_number_{{ $correction->id }}" name="ticket_number" class="input u-mono u-ltr"
                   dir="ltr" value="{{ $correction->ticket?->ticket_number }}" placeholder="TK-2026-00001">
        </x-field>
    </div>

    <x-field :name="'reason_' . $correction->id" label="السبب" required>
        <input type="text" id="reason_{{ $correction->id }}" name="reason" class="input"
               value="{{ $correction->reason }}" maxlength="255" required>
    </x-field>

    <div class="form-actions">
        <x-button variant="primary" size="sm">احفظ التعديل</x-button>
        <x-button variant="ghost" size="sm" type="button" x-on:click="editing = null">إلغاء</x-button>
    </div>
</form>
