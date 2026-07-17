@extends('layouts.app')

@section('title', 'جهات الاتصال')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">جهات الاتصال</h1>
                <p class="page-subtitle">
                    كل جهات الاتصال في كل الشركات — {{ $contacts->total() }} جهة. ابحث بالاسم أو رقم
                    الموظف أو الإيميل أو التليفون من غير ما تعرف الشركة.
                </p>
            </div>
            <div class="page__actions">
                <x-button variant="ghost" :href="route('admin.companies.index')">الشركات</x-button>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <form method="GET" action="{{ route('admin.contacts.index') }}" class="filters">
            <input
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                placeholder="ابحث بالاسم أو رقم الموظف أو الإيميل أو التليفون"
                class="input filters__search"
                autofocus
            >

            <select name="company" class="select filters__select">
                <option value="">كل الشركات</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) ($filters['company'] ?? '') === (string) $company->id)>
                        {{ $company->name }}
                    </option>
                @endforeach
            </select>

            <select name="status" class="select filters__select">
                <option value="">كل الحالات</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>مفعّلة</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>موقوفة</option>
            </select>

            <x-button variant="secondary">فلترة</x-button>

            @if (array_filter($filters))
                <x-button variant="ghost" :href="route('admin.contacts.index')">مسح</x-button>
            @endif
        </form>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>الاسم</th>
                            <th>الشركة</th>
                            <th>رقم الموظف (ERP)</th>
                            <th>الإيميل</th>
                            <th>التليفون</th>
                            <th>الحالة</th>
                            <th class="table__cell--actions">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contacts as $contact)
                            <tr>
                                <td>{{ $contact->name }}</td>
                                <td>
                                    <a href="{{ route('admin.companies.show', $contact->company) }}">
                                        {{ $contact->company->name }}
                                    </a>
                                </td>
                                <td class="u-mono u-ltr">{{ $contact->erp_employee_id }}</td>
                                <td class="u-ltr">{{ $contact->email ?: '—' }}</td>
                                <td class="u-mono u-ltr">{{ $contact->phone ?: '—' }}</td>
                                <td>
                                    @if ($contact->is_active)
                                        <x-badge variant="resolved" dot>مفعّلة</x-badge>
                                    @else
                                        <x-badge variant="neutral" dot>موقوفة</x-badge>
                                    @endif
                                </td>
                                <td class="table__cell--actions">
                                    <x-button variant="ghost" size="sm"
                                              :href="route('admin.companies.contacts.edit', [$contact->company, $contact])">
                                        تعديل
                                    </x-button>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="7">
                                    {{ array_filter($filters) ? 'مفيش نتايج للفلاتر دي.' : 'مفيش جهات اتصال لسه.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $contacts->links() }}
    </div>
@endsection
