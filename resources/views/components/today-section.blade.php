@props([
    /* The question this section answers — F22.1 defines exactly four. */
    'question',
    'hint',
])

<section {{ $attributes->class(['card', 'today__section']) }}>
    <header class="card__header">
        <div class="today__q">
            <span class="today__q-text">{{ $question }}</span>
            <span class="today__q-hint">{{ $hint }}</span>
        </div>
    </header>

    {{ $slot }}
</section>
