<form method="GET" action="{{ route('admin.users.index') }}" class="filters">
    <input
        type="search"
        name="q"
        value="{{ $filters['q'] ?? '' }}"
        placeholder="{{ __('ابحث بالاسم أو البريد الإلكتروني') }}"
        class="input filters__search"
    >

    <select name="role" class="select filters__select">
        <option value="">{{ __('جميع الأدوار') }}</option>
        @foreach ($roles as $role)
            <option value="{{ $role->id }}" @selected((string) ($filters['role'] ?? '') === (string) $role->id)>
                {{ $role->label() }}
            </option>
        @endforeach
    </select>

    <select name="status" class="select filters__select">
        <option value="">{{ __('جميع الحالات') }}</option>
        <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('مفعّل') }}</option>
        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('موقوف') }}</option>
        <option value="deleted" @selected(($filters['status'] ?? '') === 'deleted')>{{ __('محذوف') }}</option>
    </select>

    <x-button variant="secondary">{{ __('تصفية') }}</x-button>

    @if (array_filter($filters))
        <x-button variant="ghost" :href="route('admin.users.index')">{{ __('مسح') }}</x-button>
    @endif
</form>
