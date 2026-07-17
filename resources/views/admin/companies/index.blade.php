@extends('layouts.app')

@section('title', 'الشركات')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">الشركات</h1>
                <p class="page-subtitle">{{ $companies->total() }} شركة.</p>
            </div>
            <div class="page__actions">
                <x-button variant="ghost" :href="route('admin.contacts.index')">جهات الاتصال</x-button>
                <x-button variant="ghost" :href="route('admin.import.index')">استيراد من Excel</x-button>
                <x-button variant="primary" :href="route('admin.companies.create')">شركة جديدة</x-button>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        @include('admin.companies.partials._filters', ['filters' => $filters])

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>الشركة</th>
                            <th>الكود</th>
                            <th>جهات الاتصال</th>
                            <th>الحالة</th>
                            <th class="table__cell--actions">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($companies as $company)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.companies.show', $company) }}">{{ $company->name }}</a>
                                </td>
                                <td class="u-mono u-ltr">{{ $company->code }}</td>
                                <td class="u-nums">{{ $company->contacts_count }}</td>
                                <td>
                                    @if ($company->is_active)
                                        <x-badge variant="resolved" dot>مفعّلة</x-badge>
                                    @else
                                        <x-badge variant="neutral" dot>موقوفة</x-badge>
                                    @endif
                                </td>
                                <td class="table__cell--actions">
                                    <div class="row">
                                        <x-button variant="ghost" size="sm" :href="route('admin.companies.show', $company)">
                                            جهات الاتصال
                                        </x-button>
                                        <x-button variant="ghost" size="sm" :href="route('admin.companies.edit', $company)">تعديل</x-button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="5">
                                    {{ array_filter($filters) ? 'مفيش نتايج للفلاتر دي.' : 'مفيش شركات لسه. ضيف واحدة أو استورد شيت.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $companies->links() }}
    </div>
@endsection
