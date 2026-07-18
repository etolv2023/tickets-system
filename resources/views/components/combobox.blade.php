@props([
    'name',
    /* companies | contacts | users | labels | tickets */
    'resource',
    'label' => null,
    'value' => null,
    /* The current selection's text, so an edit form opens showing a name
       rather than an id it would have to fetch to resolve. */
    'selected' => null,
    /* id of another field this one depends on — contacts under a company. */
    'scope' => null,
    'placeholder' => 'اكتب للبحث…',
    'required' => false,
    'hint' => null,
])

@php
    $id = $attributes->get('id', $name);
    $current = old($name, $value);
@endphp

{{--
    A type-ahead replacement for a <select> whose options come from the
    database. The value posts through a hidden input, so validation, old() and
    the surrounding form behave exactly as they would with a native select.
--}}

{{-- $attributes is spread onto the root so a caller can attach @change and hear
     the selection: select() dispatches change on the hidden input and it
     bubbles up to here. --}}
<div {{ $attributes->except('id')->class('field') }} x-data="combobox({
        resource: @js($resource),
        value: @js($current),
        label: @js($selected),
        scope: @js($scope),
     })" @click.outside="close()">

    @if ($label)
        <label class="field__label" for="{{ $id }}-input">
            {{ $label }}
            @if ($required)<span class="field__required">*</span>@endif
        </label>
    @endif

    <div class="combobox" x-ref="anchor">
        {{-- The id lives on the hidden input, not the text box: a dependent
             combobox (contacts under a company) resolves its parent by id and
             needs the VALUE, not what is typed. --}}
        <input type="hidden" id="{{ $id }}" name="{{ $name }}" x-ref="field" :value="value">

        <input type="text" id="{{ $id }}-input" autocomplete="off"
               role="combobox" aria-expanded="false" aria-autocomplete="list"
               :aria-expanded="open.toString()"
               @class(['input', 'select--invalid' => $errors->has($name)])
               placeholder="{{ $placeholder }}"
               x-model="query"
               @input="onInput()"
               @focus="open = true; if (!results.length) search()"
               @keydown.arrow-down.prevent="move(1)"
               @keydown.arrow-up.prevent="move(-1)"
               @keydown.enter.prevent="choose()"
               @keydown.escape="close()">

        <button type="button" class="combobox__clear" x-show="value" x-cloak
                @click="clear()" aria-label="امسح الاختيار">
            <x-icon name="close" size="0.85em" />
        </button>

        {{-- Fixed-positioned and placed by measurement so an ancestor with
             overflow:hidden (every .card has it, for its rounded corners)
             cannot slice it off. --}}
        <ul class="combobox__list" x-ref="list" x-show="open" x-cloak role="listbox">
            <template x-for="(item, i) in results" :key="item.id">
                <li role="option" :aria-selected="(i === highlighted).toString()"
                    @class(['combobox__item'])
                    :class="{ 'is-active': i === highlighted }"
                    @mouseenter="highlighted = i" @click="select(item)">
                    <span class="combobox__label" x-text="item.label"></span>
                    <span class="combobox__hint u-mono" x-show="item.hint" x-text="item.hint"></span>
                </li>
            </template>

            <li class="combobox__empty" x-show="loading">بيدوّر…</li>
            <li class="combobox__empty" x-show="showEmpty">مفيش نتايج.</li>
        </ul>
    </div>

    @if ($hint)
        <p class="field__hint">{{ $hint }}</p>
    @endif

    @error($name)
        <p class="field__error">{{ $message }}</p>
    @enderror
</div>
