@props([
    'name',
    'value' => null,
    'placeholder' => 'اكتب هنا…',
    /* A shorter toolbar for the client portal: someone writing to support
       needs emphasis, a list and a link — headings and code blocks are the
       staff editor's job. */
    'simple' => false,
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
        simple: @js((bool) $simple),
    })"
    class="editor"
>
    <div x-ref="editor"></div>
    <input type="hidden" name="{{ $name }}" x-ref="input" value="{{ $current }}">

    {{-- Says where a pasted image went. Without it the paste looks like it did
         nothing: the picture is not in the text, and the attachment list is
         further down the page than the eye follows. --}}
    <p class="editor__notice" x-show="notice" x-text="notice" x-cloak
       role="status" aria-live="polite"></p>
</div>

@error($name)
    <p class="field__error">{{ $message }}</p>
@enderror
