{{-- The distribution block on the create form (F06.3). Assigning right away
     means the starter subtask exists from the ticket's first moment.

     Fully role-based (2026-07-24): one dropdown per assignable role, nothing
     hardcoded — the exact same set of fields the ticket page shows. --}}
<x-card title="التوزيع (اختياري)">
    <p class="field__hint">لو التذكرة فيتشر أو موديول جديد، التوزيع بيستنى موافقة الأدمن الأول.</p>

    <div class="form-grid">
        @forelse ($assignable['roles'] as $entry)
            <x-field name="role_assignments[{{ $entry['role']->id }}]" :label="$entry['role']->name_ar">
                <select id="role_assignments_{{ $entry['role']->id }}" name="role_assignments[{{ $entry['role']->id }}]" class="select">
                    <option value="">— مفيش —</option>
                    @foreach ($entry['candidates'] as $person)
                        <option value="{{ $person->id }}" @selected(old("role_assignments.{$entry['role']->id}") == $person->id)>{{ $person->name }}</option>
                    @endforeach
                </select>
            </x-field>
        @empty
            <p class="field__hint">مفيش أدوار متاحة للتوزيع.</p>
        @endforelse
    </div>
</x-card>
