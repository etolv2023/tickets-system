@extends('layouts.app')

@section('title', $company->name)

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">
                    {{ $company->name }}
                    @unless ($company->is_active)
                        <x-badge variant="neutral">موقوفة</x-badge>
                    @endunless
                </h1>
                <p class="page-subtitle">
                    الكود: <span class="u-mono u-ltr">{{ $company->code }}</span>
                </p>
            </div>
            <div class="page__actions">
                <x-button variant="ghost" :href="route('admin.companies.edit', $company)">تعديل الشركة</x-button>
                <x-button variant="primary" :href="route('admin.companies.contacts.create', $company)">جهة اتصال جديدة</x-button>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        @if ($company->notes)
            <x-card title="ملاحظات">
                <p>{{ $company->notes }}</p>
            </x-card>
        @endif

        <x-card title="جهات الاتصال" flush>
            <x-slot:actions>
                <span class="u-subtle">{{ $contacts->total() }}</span>
            </x-slot:actions>

            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>الاسم</th>
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
                                    <div class="row">
                                        <x-button variant="ghost" size="sm"
                                                  :href="route('admin.companies.contacts.edit', [$company, $contact])">تعديل</x-button>
                                        <form method="POST"
                                              action="{{ route('admin.companies.contacts.destroy', [$company, $contact]) }}"
                                              onsubmit="return confirm('متأكد إنك عايز تحذف «{{ $contact->name }}»؟')">
                                            @csrf
                                            @method('DELETE')
                                            <x-button variant="ghost" size="sm">حذف</x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="6">مفيش جهات اتصال للشركة دي لسه.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $contacts->links() }}
    </div>
@endsection
