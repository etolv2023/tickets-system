@php
    $step = match ($view) {
        'day' => ['prev' => $anchor->subDay(), 'next' => $anchor->addDay()],
        'week' => ['prev' => $anchor->subWeek(), 'next' => $anchor->addWeek()],
        default => ['prev' => $anchor->subMonth(), 'next' => $anchor->addMonth()],
    };

    $keep = fn (array $extra) => array_merge(array_filter($filters), ['view' => $view], $extra);
@endphp

<div class="cal__bar">
    <div class="row">
        <x-button variant="ghost" size="sm" :href="route($routeName, $keep(['date' => $step['prev']->toDateString()]))">
            ←
        </x-button>
        <x-button variant="secondary" size="sm" :href="route($routeName, $keep([]))">النهاردة</x-button>
        <x-button variant="ghost" size="sm" :href="route($routeName, $keep(['date' => $step['next']->toDateString()]))">
            →
        </x-button>
    </div>

    <div class="cal__views">
        @foreach (['month' => 'شهر', 'week' => 'أسبوع', 'day' => 'يوم', 'timeline' => 'جدول زمني'] as $key => $label)
            <x-button
                :variant="$view === $key ? 'secondary' : 'ghost'"
                size="sm"
                :href="route($routeName, array_merge(array_filter($filters), ['view' => $key, 'date' => $anchor->toDateString()]))"
            >{{ $label }}</x-button>
        @endforeach
    </div>
</div>
