@extends('layouts.app')

@section('title', __('ربط حسابات GitHub'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('ربط حسابات GitHub') }}</h1>
                <p class="page-subtitle">{{ __('يُستخدم GitHub للاكتشاف فقط؛ بعد الربط تعتمد جميع العمليات وعوامل التصفية على مستخدم النظام.') }}</p>
            </div>
            <form method="POST" action="{{ route('admin.github-identities.sync') }}">
                @csrf
                <x-button variant="secondary"><x-icon name="refresh" class="btn__icon" /> {{ __('مزامنة GitHub والحسابات') }}</x-button>
            </form>
        </div>

        @if(session('status')) <x-alert variant="success">{{ session('status') }}</x-alert> @endif
        <x-form-errors />

        <x-card>
            <form method="POST" action="{{ route('admin.github-identities.store') }}" class="filters__bar">
                @csrf
                <select name="github_login" class="select" required>
                    <option value="">{{ __('حساب GitHub غير مربوط') }}</option>
                    @foreach($availableLogins as $login)<option value="{{ $login }}">{{ '@' . $login }}</option>@endforeach
                </select>
                <select name="user_id" class="select" required>
                    <option value="">{{ __('مستخدم نظام غير مربوط') }}</option>
                    @foreach($availableUsers as $user)<option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>@endforeach
                </select>
                <x-button>{{ __('إضافة الربط') }}</x-button>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead><tr><th>{{ __('مستخدم النظام') }}</th><th>{{ __('البريد الإلكتروني') }}</th><th>{{ __('حساب GitHub') }}</th><th>{{ __('الحالة') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($mappings as $mapping)
                        <tr>
                            <td>{{ $mapping->name }}</td>
                            <td class="u-ltr">{{ $mapping->email }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.github-identities.update', $mapping) }}" class="filters__bar">
                                    @csrf @method('PUT')
                                    <select name="github_login" class="select" required>
                                        <option value="{{ $mapping->github_login }}">{{ '@' . $mapping->github_login }} — {{ __('الحالي') }}</option>
                                        @foreach($availableLogins as $login)<option value="{{ $login }}">{{ '@' . $login }}</option>@endforeach
                                    </select>
                                    <x-button size="sm">{{ __('تعديل') }}</x-button>
                                </form>
                            </td>
                            <td><x-badge :variant="$mapping->is_active ? 'green' : 'slate'">{{ $mapping->is_active ? __('نشط') : __('موقوف') }}</x-badge></td>
                            <td>
                                <form method="POST" action="{{ route('admin.github-identities.destroy', $mapping) }}" onsubmit="return confirm({{ Js::from(__('فك ربط :name عن ‎@:login؟', ['name' => $mapping->name, 'login' => $mapping->github_login])) }})">
                                    @csrf @method('DELETE')
                                    <x-button variant="danger" size="sm">{{ __('فك الربط') }}</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="u-subtle">{{ __('لا توجد حسابات مرتبطة بعد.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
@endsection
