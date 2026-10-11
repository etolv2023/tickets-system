{{-- Filters live in the query string so a filtered link can be shared. F03.1 --}}
<form method="GET" action="{{ route('admin.companies.index') }}" class="filters">
    <input
        type="search"
        name="q"
        value="{{ $filters['q'] ?? '' }}"
        placeholder="{{ __('ابحث بالاسم أو الكود') }}"
        class="input filters__search"
    >

    <select name="status" class="select filters__select">
        <option value="">{{ __('جميع الحالات') }}</option>
        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('مفعّلة') }}</option>
        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('موقوفة') }}</option>
    </select>

    <x-button variant="secondary">{{ __('تصفية') }}</x-button>

    @if (array_filter($filters))
        <x-button variant="ghost" :href="route('admin.companies.index')">{{ __('مسح') }}</x-button>
    @endif
</form>
