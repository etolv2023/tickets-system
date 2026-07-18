@props([
    'title',
    /* Stable key — the open/closed state is remembered under it. */
    'name',
    'open' => true,
    /* Every group gets one, so the heading reads as its own row rather than
       plain text sitting above a list — the same visual language as a link. */
    'icon',
])

{{--
    A foldable group of nav links.

    The sidebar reached twenty-six items in one flat list with a single
    heading, which is a scroll, not a menu. Grouping alone would not fix it —
    twenty-six items are twenty-six items — so each group folds, and remembers
    whether it was folded. Most people work in two of these and never open the
    rest.

    The state lives in localStorage rather than the session: it is a preference
    about this browser, and it must apply before any request is made.
--}}

<div class="nav-group" data-name="{{ $name }}" x-data="{
        {{-- A stored choice wins; with nothing stored the group falls back to
             its declared default, which is how الأرقام and الإدارة start
             folded instead of every group starting open. --}}
        open: localStorage.getItem('nav.{{ $name }}')
            ? localStorage.getItem('nav.{{ $name }}') === 'open'
            : {{ $open ? 'true' : 'false' }},
        toggle() {
            this.open = ! this.open;
            localStorage.setItem('nav.{{ $name }}', this.open ? 'open' : 'closed');
        },
     }"
     {{-- Auto-open ONLY the first time — never opening a fresh visit on the
          page you're on would be absurd, but forcing it open on every visit
          made the toggle look broken: the chevron flipped closed while the
          body stayed put, because x-show ORed in "contains [aria-current]"
          unconditionally. Once a choice is stored, it's the user's call, even
          for the group holding the current page. --}}
     x-init="if (localStorage.getItem('nav.{{ $name }}') === null && $el.querySelector('[aria-current]')) open = true">
    <button type="button" class="nav-group__head" @click="toggle()"
            :aria-expanded="open ? 'true' : 'false'">
        <span class="nav-group__heading">
            <x-icon :name="$icon" class="nav-group__icon" />
            <span class="nav-group__title">{{ $title }}</span>
        </span>
        <x-icon name="chevron-up" size="0.75em" class="nav-group__chevron" />
    </button>

    <div class="nav-group__body" x-show="open">
        {{ $slot }}
    </div>
</div>
