{{-- $assignable comes from the controller: one query paid once, and only
     for someone who can actually assign. No query lives in a view. § 3

     Fully role-based (2026-07-24): one dropdown per role an admin opted into
     the distribution panel — no hardcoded frontend/backend/tester/devops
     fields any more, so nothing shows up twice. --}}
<x-collapsible-card title="التوزيع">
    @error('assign')
        <x-alert variant="error">{{ $message }}</x-alert>
    @enderror

    <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="form-stack">
        @csrf

        @php $currentRoleAssignments = $ticket->roleAssignments->pluck('user_id', 'role_id'); @endphp
        @forelse ($assignable['roles'] as $entry)
            <x-field name="role_assignments[{{ $entry['role']->id }}]" :label="$entry['role']->name_ar">
                <select id="role_assignments_{{ $entry['role']->id }}" name="role_assignments[{{ $entry['role']->id }}]" class="select">
                    <option value="">— مفيش —</option>
                    @foreach ($entry['candidates'] as $person)
                        <option value="{{ $person->id }}" @selected(($currentRoleAssignments[$entry['role']->id] ?? null) === $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </x-field>
        @empty
            <p class="field__hint">مفيش أدوار متاحة للتوزيع. فعّل «تظهر في التوزيع» على الأدوار من إدارة الأدوار.</p>
        @endforelse

        <x-button variant="primary" size="sm" block>احفظ التوزيع</x-button>
    </form>
</x-collapsible-card>
