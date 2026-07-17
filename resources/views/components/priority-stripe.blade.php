@props(['priority'])

@php
    /* Accepts the Priority enum or its raw value. */
    $value = $priority instanceof \App\Enums\Priority ? $priority->value : $priority;
    $label = $priority instanceof \App\Enums\Priority ? $priority->label() : $value;
@endphp

{{-- Decorative to the eye, meaningful to a screen reader: the label carries
     what the colour says. --}}
<span {{ $attributes->class(['stripe', 'stripe--' . $value]) }} role="img" aria-label="أولوية: {{ $label }}"></span>
