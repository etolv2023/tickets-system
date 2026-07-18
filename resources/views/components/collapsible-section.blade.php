@props([
    'title',
    /* Shown next to the title, e.g. "11/11" or a status badge. */
    'meta' => null,
    'open' => true,
])

{{--
    A bordered block that folds behind a chevron. The lighter sibling of
    <x-collapsible-card>: that one IS a card, this one lives inside one.

    Used wherever a screen is a stack of independently-readable groups — the
    permission groups on a role, the outgoing transitions of a status. They sit
    one under the other at full width rather than in a grid, because a grid
    makes you hunt for which column you were reading.
--}}

<section {{ $attributes->class('fold') }} x-data="{ open: {{ $open ? 'true' : 'false' }} }">
    <header class="fold__head">
        <button type="button" class="fold__toggle" @click="open = !open"
                :aria-expanded="open ? 'true' : 'false'">
            <x-icon name="chevron-up" size="0.85em" class="fold__chevron" />
        </button>

        <h3 class="fold__title">{{ $title }}</h3>

        @if ($meta)
            <span class="fold__meta">{{ $meta }}</span>
        @endif

        @isset($actions)
            <div class="fold__actions">{{ $actions }}</div>
        @endisset
    </header>

    {{-- Plain x-show, not @alpinejs/collapse: no new package (CLAUDE.md § 1),
         and the same idiom <x-collapsible-card> already uses. --}}
    <div class="fold__body" x-show="open">
        {{ $slot }}
    </div>
</section>
