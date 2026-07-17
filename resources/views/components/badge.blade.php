@props([
    /* One of: urgent high medium low resolved blocked bug inquiry feature module neutral */
    'variant' => 'neutral',
    'dot' => false,
    'solid' => false,
])

<span {{ $attributes->class([
    'badge',
    'badge--' . $variant,
    'badge--solid' => $solid,
]) }}>
    @if ($dot)
        <span class="badge__dot"></span>
    @endif
    {{ $slot }}
</span>
