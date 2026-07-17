{{-- A cell with no rule stays empty: F18's matrix has dashes, and a dash means
     "this side earns nothing here", not "zero points". --}}
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
            @change="$store.matrix ?? null; save(@js($type), @js($scope), @js($side), points, active)"
            :disabled="!active && exists"
        >

        <label class="matrix-cell__switch" title="مفعّلة؟">
            <input
                type="checkbox"
                class="checkbox"
                x-model="active"
                @change="save(@js($type), @js($scope), @js($side), points, active)"
            >
        </label>
    </div>
</div>
