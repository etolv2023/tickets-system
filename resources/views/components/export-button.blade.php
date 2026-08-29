@props([
    /* Route name of the export endpoint. */
    'route',
    /* Extra route parameters this screen needs (a user id, a week…). */
    'params' => [],
    'label' => 'تصدير Excel',
])

@php
    /*
     * The file mirrors the screen: whatever is filtering the page right now
     * filters the export too. Reading the live query string here is what makes
     * that true without every screen restating its own filter list — and a
     * paginator page number riding along is harmless, since no export reads it.
     */
    $query = array_merge(request()->query(), $params);
@endphp

<a href="{{ route($route, $query) }}" {{ $attributes->class('btn btn--secondary btn--sm') }}>
    <x-icon name="download" class="btn__icon" />
    {{ $label }}
</a>
