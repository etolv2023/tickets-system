@props([
    /* info | success | error */
    'variant' => 'info',
])

<div {{ $attributes->class(['alert', 'alert--' . $variant => $variant !== 'info']) }} role="{{ $variant === 'error' ? 'alert' : 'status' }}">
    <svg class="alert__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        @if ($variant === 'success')
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd" />
        @elseif ($variant === 'error')
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 5a1 1 0 012 0v5a1 1 0 11-2 0V5zm1 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
        @else
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 11-2 0 1 1 0 012 0zm-1 3a1 1 0 00-1 1v3a1 1 0 102 0v-3a1 1 0 00-1-1z" clip-rule="evenodd" />
        @endif
    </svg>
    <div>{{ $slot }}</div>
</div>
