{{-- Same four fields as _assign.blade.php's panel (F06.3), but as part of the
     create form itself rather than a separate POST — assigning right away
     means the starter subtask exists from the ticket's first moment. --}}
<x-card title="التوزيع (اختياري)">
    <p class="field__hint">لو التذكرة فيتشر أو موديول جديد، التوزيع بيستنى موافقة الأدمن الأول.</p>

    <div class="form-grid">
        <x-field name="assigned_frontend_id" label="مبرمج فرونت">
            <select id="assigned_frontend_id" name="assigned_frontend_id" class="select">
                <option value="">— مفيش —</option>
                @foreach ($assignable['frontend'] as $dev)
                    <option value="{{ $dev->id }}" @selected(old('assigned_frontend_id') == $dev->id)>{{ $dev->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="assigned_backend_id" label="مبرمج باك">
            <select id="assigned_backend_id" name="assigned_backend_id" class="select">
                <option value="">— مفيش —</option>
                @foreach ($assignable['backend'] as $dev)
                    <option value="{{ $dev->id }}" @selected(old('assigned_backend_id') == $dev->id)>{{ $dev->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="tester_id" label="تيستر" hint="لو سيبتها فاضية، التذكرة هتروح للحل مباشرة بعد التطوير.">
            <select id="tester_id" name="tester_id" class="select">
                <option value="">— مفيش —</option>
                @foreach ($assignable['testers'] as $tester)
                    <option value="{{ $tester->id }}" @selected(old('tester_id') == $tester->id)>{{ $tester->name }}</option>
                @endforeach
            </select>
        </x-field>

        <x-field name="devops_id" label="ديف أوبس" hint="مالوش زرار «بدأت/خلصت» — التذكرة بتوصل «تم التطوير» من غيره.">
            <select id="devops_id" name="devops_id" class="select">
                <option value="">— مفيش —</option>
                @foreach ($assignable['devops'] as $person)
                    <option value="{{ $person->id }}" @selected(old('devops_id') == $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </x-field>
    </div>
</x-card>
