@php
    $activeFilterCount = collect($filters)->filter(
        fn ($value) => is_array($value) ? array_filter($value) !== [] : filled($value)
    )->count();
@endphp

<form method="GET" action="{{ route('tickets.index') }}" class="filters filters--tickets"
      x-data="{ panelOpen: false }">
    @include('tickets.partials._filters-basic')

    <div class="filters__panel" x-show="panelOpen" x-cloak @keydown.escape.window="panelOpen = false">
        <div class="filters__panel-head">
            <div>
                <h2 class="filters__panel-title">تصفية التذاكر</h2>
                <p class="filters__panel-hint">اختار اللي محتاجه بس؛ باقي الفلاتر متقسمة تحت حسب الاستخدام.</p>
            </div>
            <button type="button" class="btn btn--ghost btn--icon" @click="panelOpen = false" aria-label="إغلاق الفلاتر">
                <x-icon name="close" />
            </button>
        </div>

        @include('tickets.partials._filters-narrow')
        @include('tickets.partials._filters-advanced')

        <div class="filters__panel-actions">
            <x-button variant="primary">تطبيق الفلاتر</x-button>
            @if ($activeFilterCount)
                <x-button variant="ghost" :href="route('tickets.index')">مسح الكل</x-button>
            @endif
        </div>
    </div>
</form>
