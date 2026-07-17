@php
    $steps = [
        1 => 'المتطلبات',
        2 => 'قاعدة البيانات',
        3 => 'الجداول',
        4 => 'حساب المدير',
        5 => 'الإنهاء',
    ];
@endphp

<ol class="steps">
    @foreach ($steps as $number => $label)
        <li @class([
            'steps__item',
            'steps__item--current' => $number === $current,
            'steps__item--done' => $number < $current,
        ])>
            <span class="steps__dot">
                @if ($number < $current)
                    <svg viewBox="0 0 20 20" fill="currentColor" width="12" height="12" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-8 8a1 1 0 01-1.4 0l-4-4a1 1 0 011.4-1.4L8 12.6l7.3-7.3a1 1 0 011.4 0z" clip-rule="evenodd" />
                    </svg>
                @else
                    {{ $number }}
                @endif
            </span>
            <span class="steps__label">{{ $label }}</span>
            @unless ($loop->last)
                <span class="steps__bar"></span>
            @endunless
        </li>
    @endforeach
</ol>
