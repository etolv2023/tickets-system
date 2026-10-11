@extends('layouts.app')

@section('title', __('الفروع المخالفة'))

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">{{ __('الفروع المخالفة') }}</h1>
                <p class="page-subtitle">{{ __('فروع GitHub التي لا يبدأ اسمها برقم تذكرة. تاريخ الإنشاء هنا هو أول مرة رآها فيها النظام، لأن GitHub لا يرسل تاريخ إنشاء الفرع.') }}</p>
            </div>
            <form method="POST" action="{{ route('github.sync') }}">
                @csrf
                <x-button variant="secondary"><x-icon name="refresh" class="btn__icon" /> {{ __('مزامنة GitHub والحسابات') }}</x-button>
            </form>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        <x-form-errors />
        @if(auth()->user()->hasPermission('github.branches.delete') && !$writeConfigured)
            <x-alert>{{ __('حذف الفروع معطّل حتى تضيف') }} <span class="u-mono u-ltr">GITHUB_WRITE_TOKEN</span> {{ __('على الخادم.') }}</x-alert>
        @endif

        <x-card>
            <form method="GET" class="filters">
                <div class="filters__bar">
                    <input name="q" value="{{ request('q') }}" placeholder="{{ __('اسم الفرع') }}" class="input">
                    <select name="author_user" class="select" aria-label="{{ __('صاحب الفرع') }}">
                        <option value="">{{ __('جميع أصحاب الفروع') }}</option>
                        @foreach($filterUsers as $user)
                            <option value="{{ $user->id }}" @selected((int) request('author_user') === $user->id)>
                                {{ $user->name }} — {{ '@' . $user->github_login }}
                            </option>
                        @endforeach
                    </select>
                    <select name="repo" class="select">
                        <option value="">{{ __('جميع المستودعات') }}</option>
                        @foreach($repositories as $repo)
                            <option value="{{ $repo->id }}" @selected((string) request('repo') === (string) $repo->id)>{{ $repo->name }}</option>
                        @endforeach
                    </select>
                    <select name="state" class="select">
                        <option value="active" @selected(request('state', 'active') === 'active')>{{ __('موجودة') }}</option>
                        <option value="deleted" @selected(request('state') === 'deleted')>{{ __('حُذفت') }}</option>
                    </select>
                    <x-button type="submit">{{ __('تصفية') }}</x-button>
                    @if(request()->hasAny(['q', 'author_user', 'repo', 'state']))
                        <x-button variant="ghost" :href="route('github.unmatched-branches')">{{ __('مسح') }}</x-button>
                    @endif
                </div>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead><tr><th>{{ __('المستودع') }}</th><th>{{ __('الفرع') }}</th><th>{{ __('صاحبه') }}</th><th>{{ __('أول ظهور') }}</th><th>{{ __('آخر إيداع') }}</th><th>{{ __('الحالة') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($branches as $branch)
                        <tr>
                            <td>{{ $branch->repository?->name ?? '—' }}</td>
                            <td><a href="{{ $branch->url() }}" target="_blank" rel="noopener noreferrer" class="u-mono u-ltr">{{ $branch->name }}</a></td>
                            <td>
                                @if($branch->author)
                                    <x-avatar :user="$branch->author" size="sm" /> {{ $branch->author->name }}
                                    <span class="u-subtle u-ltr">{{ '@' . $branch->author_login }}</span>
                                @elseif($branch->author_login)
                                    <span class="u-mono u-ltr">{{ '@' . $branch->author_login }}</span>
                                @else — @endif
                            </td>
                            <td>{{ $branch->first_seen_at?->timezone(config('app.display_timezone'))->translatedFormat('j M Y — H:i') ?? '—' }}</td>
                            <td>{{ $branch->last_commit_at?->timezone(config('app.display_timezone'))->translatedFormat('j M Y — H:i') ?? '—' }}</td>
                            <td><x-badge :variant="$branch->state->value === 'active' ? 'red' : 'gray'">{{ $branch->state->value === 'active' ? __('مخالفة') : __('حُذفت') }}</x-badge></td>
                            <td>
                                @if($branch->state->value === 'active' && auth()->user()->hasPermission('github.branches.delete'))
                                    <form method="POST" action="{{ route('github.unmatched-branches.destroy', $branch) }}"
                                          onsubmit="const branchName = {{ Illuminate\Support\Js::from($branch->name) }}; const value = prompt({{ Js::from(__('اكتب اسم الفرع للتأكيد:')) }} + '\n' + branchName); if (value === null) return false; this.elements.confirmation.value = value;">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="confirmation">
                                        <x-button variant="danger" size="sm" type="submit" :disabled="!$writeConfigured">{{ __('حذف الفرع') }}</x-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="u-subtle">{{ __('لا توجد فروع مطابقة لعوامل التصفية.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $branches->links() }}
    </div>
@endsection
