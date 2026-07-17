@props([
    'name',
    'value' => null,
    'placeholder' => 'اكتب هنا…',
])

@php
    $current = old($name, $value) ?? '';
@endphp

{{-- The hidden input is what actually submits. Quill writes into it on every
     keystroke; the server purifies whatever arrives regardless. F04.1 --}}
<div
    x-data="editor({
        name: @js($name),
        value: @js($current),
        placeholder: @js($placeholder),
    })"
    class="editor"
>
    <div x-ref="editor"></div>
    <input type="hidden" name="{{ $name }}" x-ref="input" value="{{ $current }}">
</div>

@error($name)
    <p class="field__error">{{ $message }}</p>
@enderror
