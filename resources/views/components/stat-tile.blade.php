@props([
    /* slate | green | teal | amber | red — the hue on the 3px inline-start edge
       and on the head glyph. Never a full card wash (today.css keeps hue to the
       edge so four tiles don't fight). */
    'variant' => 'slate',
    /* An icon component name (see icon.blade.php) labelling what the figure
       counts, tinted the tile hue. Written without the angle-bracket tag on
       purpose: Blade scans a component file for x- tags before it parses these
       props, so a tag in this comment would compile into a stray render call
       and take the page down with "Undefined variable $component". */
    'icon' => null,
    'figure',
    'caption',
    /* A trailing unit on the figure (e.g. "س" for hours) — muted, smaller. */
    'unit' => null,
])

<div {{ $attributes->class(['stat-tile', 'stat-tile--' . $variant]) }}>
    <div class="stat-tile__head">
        @if ($icon)
            <x-icon :name="$icon" class="stat-tile__glyph" />
        @endif
        <span class="stat-tile__caption">{{ $caption }}</span>
    </div>
    <div class="stat-tile__figure">{{ $figure }}@if ($unit)<span class="stat-tile__unit">{{ $unit }}</span>@endif</div>
</div>
