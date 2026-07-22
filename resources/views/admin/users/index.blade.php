@extends('layouts.app')

@section('title', 'المستخدمين')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">المستخدمين</h1>
                <p class="page-subtitle">{{ $users->total() }} مستخدم.</p>
            </div>
            <div class="page__actions">
                <x-button variant="ghost" :href="route('admin.import.index')">استيراد من Excel</x-button>
                <x-button variant="primary" :href="route('admin.users.create')">مستخدم جديد</x-button>
            </div>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        @if ($errors->any())
            <x-alert variant="error">{{ $errors->first() }}</x-alert>
        @endif

        @if (session('generated_password'))
            @include('admin.users.partials._generated-password')
        @endif

        @include('admin.users.partials._filters')

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead>
                        <tr>
                            <th>المستخدم</th>
                            <th>الدور</th>
                            <th>آخر دخول</th>
                            <th>الحالة</th>
                            <th class="table__cell--actions">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td>
                                    <div class="row">
                                        <x-avatar :user="$user" />
                                        <div>
                                            <div>{{ $user->name }}</div>
                                            <div class="u-subtle u-ltr">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $user->role->name_ar }}</td>
                                <td class="u-nums">
                                    {{ $user->last_login_at?->timezone(config('app.display_timezone'))->diffForHumans() ?? 'مدخلش لسه' }}
                                </td>
                                <td>
                                    <div class="row">
                                        @if ($user->is_active)
                                            <x-badge variant="resolved" dot>مفعّل</x-badge>
                                        @else
                                            <x-badge variant="neutral" dot>موقوف</x-badge>
                                        @endif
                                        @if ($user->must_change_password)
                                            <x-badge variant="medium">لازم يغيّر كلمة السر</x-badge>
                                        @endif
                                    </div>
                                </td>
                                <td class="table__cell--actions">
                                    <x-button variant="ghost" size="sm" :href="route('admin.users.edit', $user)">تعديل</x-button>
                                </td>
                            </tr>
                        @empty
                            <tr class="table__empty">
                                <td colspan="6">
                                    {{ array_filter($filters) ? 'مفيش نتايج للفلاتر دي.' : 'مفيش مستخدمين.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $users->links() }}
    </div>
@endsection
