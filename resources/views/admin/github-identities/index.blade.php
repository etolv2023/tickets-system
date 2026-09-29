@extends('layouts.app')

@section('title', 'ربط حسابات GitHub')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">ربط حسابات GitHub</h1>
                <p class="page-subtitle">GitHub للاكتشاف فقط؛ بعد الربط كل التعامل والفلاتر يعتمدوا على مستخدم النظام.</p>
            </div>
            <form method="POST" action="{{ route('admin.github-identities.sync') }}">
                @csrf
                <x-button variant="secondary"><x-icon name="refresh" class="btn__icon" /> زامن GitHub والحسابات</x-button>
            </form>
        </div>

        @if(session('status')) <x-alert variant="success">{{ session('status') }}</x-alert> @endif
        <x-form-errors />

        <x-card>
            <form method="POST" action="{{ route('admin.github-identities.store') }}" class="filters__bar">
                @csrf
                <select name="github_login" class="select" required>
                    <option value="">حساب GitHub غير مربوط</option>
                    @foreach($availableLogins as $login)<option value="{{ $login }}">{{ '@' . $login }}</option>@endforeach
                </select>
                <select name="user_id" class="select" required>
                    <option value="">مستخدم نظام غير مربوط</option>
                    @foreach($availableUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach
                </select>
                <x-button>أضف الربط</x-button>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead><tr><th>مستخدم النظام</th><th>البريد</th><th>حساب GitHub</th><th>الحالة</th><th></th></tr></thead>
                    <tbody>
                    @forelse($mappings as $mapping)
                        <tr>
                            <td>{{ $mapping->name }}</td>
                            <td class="u-ltr">{{ $mapping->email }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.github-identities.update', $mapping) }}" class="filters__bar">
                                    @csrf @method('PUT')
                                    <select name="github_login" class="select" required>
                                        <option value="{{ $mapping->github_login }}">{{ '@' . $mapping->github_login }} — الحالي</option>
                                        @foreach($availableLogins as $login)<option value="{{ $login }}">{{ '@' . $login }}</option>@endforeach
                                    </select>
                                    <x-button size="sm">تعديل</x-button>
                                </form>
                            </td>
                            <td><x-badge :variant="$mapping->is_active ? 'green' : 'slate'">{{ $mapping->is_active ? 'نشط' : 'موقوف' }}</x-badge></td>
                            <td>
                                <form method="POST" action="{{ route('admin.github-identities.destroy', $mapping) }}" onsubmit="return confirm('تفك ربط {{ $mapping->name }} من @{{ $mapping->github_login }}؟')">
                                    @csrf @method('DELETE')
                                    <x-button variant="danger" size="sm">فك الربط</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="u-subtle">مفيش حسابات مربوطة لسه.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
