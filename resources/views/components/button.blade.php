@props([
    /* primary | secondary | ghost | danger */
    'variant' => 'secondary',
    'size' => null,
    'block' => false,
    /* Renders an <a> instead of a <button>. */
    'href' => null,
    'type' => 'submit',
])

@php
    $classes = collect([
        'btn',
        'btn--' . $variant,
        $size ? 'btn--' . $size : null,
        $block ? 'btn--block' : null,
    ])->filter()->implode(' ');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
