@props([
    /* A hue (red amber blue green slate), a role alias (urgent high medium low
       resolved progress pending assigned blocked bug inquiry feature module),
       or neutral. */
    'variant' => 'neutral',
    'dot' => false,
    'solid' => false,
    /* An icon component name. A badge that carries a glyph is readable at a
       glance rather than only on reading — which is the whole point for ticket
       type, where two types can legitimately share a hue.

       Keep component tags out of this block entirely, brackets and all: Blade
       scans the raw file for them before it ever parses the array, so one in a
       comment compiles into a render call in the middle of @props and takes
       the page down with "Undefined variable $component". */
    'icon' => null,
])

<span {{ $attributes->class([
    'badge',
    'badge--' . $variant,
    'badge--solid' => $solid,
]) }}>
    @if ($icon)
        <x-icon :name="$icon" class="badge__icon" />
    @elseif ($dot)
        <span class="badge__dot"></span>
    @endif
    {{ $slot }}
</span>
