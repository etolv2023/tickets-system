@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    /* An Alpine expression deciding `required` live, for a field whose
       obligation depends on another control on the same form — the ticket
       form's "من عميل / داخلية" toggle, say. Drives the asterisk and the
       attribute together, so the label can never promise something the input
       does not enforce. Blade alone can't do this: the answer changes after
       the page is rendered. */
    'requiredExpr' => null,
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
        @if ($required || $requiredExpr)
            <span class="field__required" aria-hidden="true"
                  @if ($requiredExpr) x-show="{{ $requiredExpr }}" @endif>*</span>
        @endif
    </label>

    @if ($slot->isEmpty())
        <input
            type="{{ $type }}"
            id="{{ $id }}"
            name="{{ $name }}"
            value="{{ old($name, $value) }}"
            @if ($requiredExpr) x-bind:required="{{ $requiredExpr }}" @elseif ($required) required @endif
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
