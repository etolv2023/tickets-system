{{-- $assignable comes from the controller: three queries paid once, and only
     for someone who can actually assign. No query lives in a view. § 3 --}}
<x-card title="التوزيع">
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

        <x-button variant="primary" size="sm" block>احفظ التوزيع</x-button>
    </form>
</x-card>
