{{-- $assignable comes from the controller: one query paid once, and only
     for someone who can actually assign. No query lives in a view. § 3 --}}
<x-collapsible-card title="التوزيع">
    @error('assign')
        <x-alert variant="error">{{ $message }}</x-alert>
    @enderror

    <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="form-stack">
        @csrf

        <x-field name="assigned_frontend_id" label="مبرمج فرونت">
            <select id="assigned_frontend_id" name="assigned_frontend_id" class="select">
                <option value="">— مفيش —</option>
                @foreach ($assignable['frontend'] as $dev)
                    <option value="{{ $dev->id }}" @selected($ticket->assigned_frontend_id === $dev->id)>{{ $dev->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="assigned_backend_id" label="مبرمج باك">
            <select id="assigned_backend_id" name="assigned_backend_id" class="select">
                <option value="">— مفيش —</option>
                @foreach ($assignable['backend'] as $dev)
                    <option value="{{ $dev->id }}" @selected($ticket->assigned_backend_id === $dev->id)>{{ $dev->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="tester_id" label="تيستر" hint="لو سيبتها فاضية، التذكرة هتروح للحل مباشرة بعد التطوير.">
            <select id="tester_id" name="tester_id" class="select">
                <option value="">— مفيش —</option>
                @foreach ($assignable['testers'] as $tester)
                    <option value="{{ $tester->id }}" @selected($ticket->tester_id === $tester->id)>{{ $tester->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="devops_id" label="ديف أوبس" hint="مالوش زرار «بدأت/خلصت» — التذكرة بتوصل «تم التطوير» من غيره.">
            <select id="devops_id" name="devops_id" class="select">
                <option value="">— مفيش —</option>
                @foreach ($assignable['devops'] as $person)
                    <option value="{{ $person->id }}" @selected($ticket->devops_id === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </x-field>

        {{-- F06 role-assignment extension: one dropdown per role an admin
             opted into the panel from /admin/roles. --}}
        @php $currentRoleAssignments = $ticket->roleAssignments->pluck('user_id', 'role_id'); @endphp
        @foreach ($assignable['roles'] as $entry)
            <x-field name="role_assignments[{{ $entry['role']->id }}]" :label="$entry['role']->name_ar">
                <select id="role_assignments_{{ $entry['role']->id }}" name="role_assignments[{{ $entry['role']->id }}]" class="select">
                    <option value="">— مفيش —</option>
                    @foreach ($entry['candidates'] as $person)
                        <option value="{{ $person->id }}" @selected(($currentRoleAssignments[$entry['role']->id] ?? null) === $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </x-field>
        @endforeach

        <x-button variant="primary" size="sm" block>احفظ التوزيع</x-button>
    </form>
</x-collapsible-card>
