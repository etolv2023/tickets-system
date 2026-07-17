@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
    /* Pass a slot to supply your own control (select, checkbox group, ...). */
])

@php
    $invalid = $errors->has($name);
    $id = $attributes->get('id', $name);
@endphp

<div class="field">
    <label class="field__label" for="{{ $id }}">
        {{ $label }}
        @if ($required)
            <span class="field__required" aria-hidden="true">*</span>
        @endif
    </label>

    @if ($slot->isEmpty())
        <input
            type="{{ $type }}"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if ($required) required @endif
            @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class(['input', 'input--invalid' => $invalid]) }}
        >
    @else
        {{ $slot }}
    @endif

    @if ($hint)
        <p class="field__hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="field__error" id="{{ $id }}-error">{{ $message }}</p>
    @enderror
</div>
