@props([
    /* The question this section answers — F22.1 defines exactly four. */
    'question',
    'hint',
    /* The section's row count, shown as a hue-tinted pill echoing the header
       pulse-chip so a section and its chip read as the same thing. */
    'count' => null,
    /* red | amber | teal | green — the hue this question owns. */
    'variant' => null,
    /* Anchor target for the header pulse-chip link. */
    'anchor' => null,
])

<section {{ $attributes->class(['card', 'today__section']) }} @if ($anchor) id="{{ $anchor }}" @endif>
    <header class="card__header">
        <div class="today__q">
            <span class="today__q-text">{{ $question }}</span>
            <span class="today__q-hint">{{ $hint }}</span>
        </div>
        <div class="row">
            @isset($action)
                {{ $action }}
            @endisset
            @if (! is_null($count))
                <span class="today__count today__count--{{ $variant }}">{{ $count }}</span>
            @endif
        </div>
    </header>

    {{ $slot }}
</section>
