<form method="GET" action="{{ route('admin.users.index') }}" class="filters">
    <input
        type="search"
        name="q"
        value="{{ $filters['q'] ?? '' }}"
        placeholder="ابحث بالاسم أو الإيميل"
        class="input filters__search"
    >

    <select name="role" class="select filters__select">
        <option value="">كل الأدوار</option>
        @foreach ($roles as $role)
            <option value="{{ $role->id }}" @selected((string) ($filters['role'] ?? '') === (string) $role->id)>
                {{ $role->name_ar }}
            </option>
        @endforeach
    </select>

    <select name="status" class="select filters__select">
        <option value="">كل الحالات</option>
        <option value="active" @selected(($filters['status'] ?? '') === 'active')>مفعّل</option>
        <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>موقوف</option>
    </select>

    <x-button variant="secondary">فلترة</x-button>

    @if (array_filter($filters))
        <x-button variant="ghost" :href="route('admin.users.index')">مسح</x-button>
    @endif
</form>
