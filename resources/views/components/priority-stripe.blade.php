@props(['priority'])

@php
    /* Accepts a PriorityValue or its raw key. The stripe colour is the
       priority's own colour token (§ 6), not the key — a custom priority's
       chosen colour renders correctly either way. */
    $value = $priority instanceof \App\Casts\PriorityValue ? $priority : \App\Casts\PriorityValue::for($priority);
@endphp

{{-- Decorative to the eye, meaningful to a screen reader: the label carries
     what the colour says. --}}
<span {{ $attributes->class(['stripe', 'stripe--' . $value->variant()]) }} role="img" aria-label="أولوية: {{ $value->label() }}"></span>
