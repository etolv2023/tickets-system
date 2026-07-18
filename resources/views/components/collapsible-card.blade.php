@props([
    'title',
    /* Rows sit flush against the border; the hairline is the divider. */
    'flush' => false,
    /* Folded by default. The aside stacks seven of these and most of them are
       reference, not action — open, they turn the column into a scroll you
       have to travel past to reach the next thing.

       Pass :open="true" for a card that is the reason you came to the page. */
    'open' => false,
    /* Overrides the storage key. Only needed when two cards on one screen
       share a title. */
    'name' => null,
])

@php
    /* A stable key for this card's remembered state. Hashed rather than
     * slugged: the titles are Arabic, and Str::slug strips non-Latin to
     * nothing, which would collapse every card onto one shared key. */
    $foldKey = 'fold.' . ($name ?? substr(md5($title), 0, 8));
@endphp

{{--
    A card whose body folds away behind a chevron in its corner. Used by the
    ticket aside, where seven fact panels stack up.

    Three things decide whether it starts open, in order:
      1. a validation error inside it — always wins, see below
      2. what you last chose for this card, from localStorage
      3. the :open default

    The chevron is chrome, not an action the card offers, so it is a bare
    .card__collapse square rather than a .btn.
--}}

<section {{ $attributes->class(['card']) }}
         x-data="{
             open: localStorage.getItem(@js($foldKey))
                 ? localStorage.getItem(@js($foldKey)) === 'open'
                 : {{ $open ? 'true' : 'false' }},
             toggle() {
                 this.open = ! this.open;
                 localStorage.setItem(@js($foldKey), this.open ? 'open' : 'closed');
             },
         }"
         {{-- A folded card that hides a validation error is worse than a long
              column: the form rejects the submit and the message is rendered
              into a hidden div, so nothing on screen says why. This overrides
              both the stored choice and the default — an error is not a
              preference. --}}
         x-init="if ($el.querySelector('.field__error, .input--invalid, .select--invalid, .textarea--invalid')) open = true">
    <header class="card__header">
        <h2 class="card__title">{{ $title }}</h2>

        <div class="row">
            @isset($actions)
                {{ $actions }}
            @endisset

            <button type="button" class="card__collapse" @click="toggle()"
                    :aria-expanded="open ? 'true' : 'false'"
                    :title="open ? 'اطوِ' : 'افتح'"
                    aria-label="طيّ أو فتح القسم">
                <x-icon name="chevron-up" size="0.85em" class="card__collapse-icon" />
            </button>
        </div>
    </header>

    {{-- x-cloak because the body now starts folded: without it the whole card
         paints open for one frame and the column jumps as Alpine boots. --}}
    <div x-show="open" x-cloak>
        <div class="card__body {{ $flush ? 'card__body--flush' : '' }}">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="card__footer">{{ $footer }}</footer>
        @endisset
    </div>
</section>
