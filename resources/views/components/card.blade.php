@props([
    'title' => null,
    /* Rows sit flush against the border; the hairline is the divider. */
    'flush' => false,
])

<section {{ $attributes->class(['card']) }}>
    @if ($title || isset($actions))
        <header class="card__header">
            <h2 class="card__title">{{ $title }}</h2>
            @isset($actions)
                <div class="row">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="card__body {{ $flush ? 'card__body--flush' : '' }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <footer class="card__footer">{{ $footer }}</footer>
    @endisset
</section>
