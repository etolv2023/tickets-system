{{-- F06 role-assignment extension — same empty-cell convention as the fixed
     matrix's _cell.blade.php: no rule means this role earns nothing here. --}}
<div class="matrix-cell" x-data="{
    points: {{ $rule?->points ?? 0 }},
    active: {{ $rule && $rule->is_active ? 'true' : 'false' }},
    exists: {{ $rule ? 'true' : 'false' }},
}">
    <div class="row">
        <input
            type="number"
            class="input matrix-cell__input"
            step="0.5"
            min="0"
            max="999"
            x-model.number="points"
            @change="saveRole(@js($type), @js($roleId), points, active)"
            :disabled="!active && exists"
        >

        <label class="matrix-cell__switch" title="مفعّلة؟">
            <input
                type="checkbox"
                class="checkbox"
                x-model="active"
                @change="saveRole(@js($type), @js($roleId), points, active)"
            >
        </label>
    </div>
</div>
