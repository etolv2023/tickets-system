@php
    // Capped so the bar can't overflow; the colour carries the overrun. F14
    $percent = $meter['capacity'] > 0
        ? min(100, (int) round($meter['load'] / $meter['capacity'] * 100))
        : 100;
@endphp

<span class="cap cap--{{ $meter['state'] }}"
      title="{{ __(':load من :capacity ساعة', ['load' => rtrim(rtrim(number_format($meter['load'], 2), '0'), '.'), 'capacity' => rtrim(rtrim(number_format($meter['capacity'], 2), '0'), '.')]) }}">
    <span class="cap__bar">
        <span class="cap__fill" style="--value: {{ $percent }}%"></span>
    </span>
    {{ __(':hoursس', ['hours' => rtrim(rtrim(number_format($meter['load'], 1), '0'), '.')]) }}
</span>
