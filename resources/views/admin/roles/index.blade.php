@extends('layouts.app')

@section('title', 'الأدوار والصلاحيات')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">الأدوار والصلاحيات</h1>
                <p class="page-subtitle">الصلاحيات في قاعدة البيانات — تقدر تغيّر أي تركيبة من غير ما نلمس كود.</p>
            </div>
            <div class="page__actions">
                <x-button variant="primary" :href="route('admin.roles.create')">دور جديد</x-button>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>الدور</th>
                            <th>الكود</th>
                            <th>الصلاحيات</th>
                            <th>المستخدمين</th>
                            <th class="table__cell--actions">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            <tr>
                                <td>
                                    {{ $role->name_ar }}
                                    @if ($role->is_system)
                                        <x-badge variant="neutral">أساسي</x-badge>
                                    @endif
                                    @if ($role->assignable_on_tickets)
                                        <x-badge variant="neutral">في التوزيع</x-badge>
                                    @endif
                                </td>
                                <td class="u-mono u-ltr">{{ $role->key }}</td>
                                <td class="u-nums">{{ $role->permissions_count }}</td>
                                <td class="u-nums">{{ $role->users_count }}</td>
                                <td class="table__cell--actions">
                                    <div class="row">
                                        <x-button variant="ghost" size="sm" :href="route('admin.roles.edit', $role)">تعديل</x-button>

                                        @if ($role->is_system)
                                            <span class="settings__locked">مينفعش يتحذف</span>
                                        @elseif ($role->users_count > 0)
                                            <span class="settings__locked">عليه مستخدمين</span>
                                        @else
                                            <form
                                                method="POST"
                                                action="{{ route('admin.roles.destroy', $role) }}"
                                                onsubmit="return confirm('متأكد إنك عايز تحذف دور «{{ $role->name_ar }}»؟')"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <x-button variant="ghost" size="sm">حذف</x-button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="5">مفيش أدوار.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
