@extends('layouts.app')

@section('title', 'البرانشات المخالفة')

@section('content')
    <div class="page">
        <div class="page__head">
            <div>
                <h1 class="page-title">البرانشات المخالفة</h1>
                <p class="page-subtitle">برانشات GitHub التي لا يبدأ اسمها برقم تذكرة. تاريخ الإنشاء هنا هو أول مرة رآها النظام، لأن GitHub لا يرسل تاريخ إنشاء للبرانش.</p>
            </div>
            <form method="POST" action="{{ route('github.sync') }}">
                @csrf
                <x-button variant="secondary"><x-icon name="refresh" class="btn__icon" /> زامن GitHub والحسابات</x-button>
            </form>
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif
        <x-form-errors />
        @if(auth()->user()->hasPermission('github.branches.delete') && !$writeConfigured)
            <x-alert>حذف البرانشات مقفول لحد ما تضيف <span class="u-mono u-ltr">GITHUB_WRITE_TOKEN</span> على السيرفر.</x-alert>
        @endif

        <x-card>
            <div class="stack stack--tight">
                <div>
                    <strong>ربط حسابات GitHub بالناس</strong>
                    <p class="u-subtle">اختار الحساب اللي ظهر في البرانشات أو الـ PRs، واربطه بمستخدم النظام. الربط ده بيتستخدم كمان لمعرفة صاحب الاكسبشن.</p>
                </div>
                <form method="POST" action="{{ route('github.accounts.link') }}" class="filters__bar">
                    @csrf
                    <select name="github_login" class="select" required aria-label="حساب GitHub">
                        <option value="">حساب GitHub المكتشف</option>
                        @foreach($githubLogins as $login)
                            <option value="{{ $login }}">{{ '@' . $login }}</option>
                        @endforeach
                    </select>
                    <select name="user_id" class="select" required aria-label="مستخدم النظام">
                        <option value="">اختار الشخص في النظام</option>
                        @foreach($availableUsers as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <x-button type="submit">احفظ الربط</x-button>
                </form>
            </div>
        </x-card>

        <x-card>
            <form method="GET" class="filters">
                <div class="filters__bar">
                    <input name="q" value="{{ request('q') }}" placeholder="اسم البرانش" class="input">
                    <select name="author_user" class="select" aria-label="صاحب البرانش">
                        <option value="">كل أصحاب البرانشات</option>
                        @foreach($filterUsers as $user)
                            <option value="{{ $user->id }}" @selected((int) request('author_user') === $user->id)>
                                {{ $user->name }} — {{ '@' . $user->github_login }}
                            </option>
                        @endforeach
                    </select>
                    <select name="repo" class="select">
                        <option value="">كل الريبوز</option>
                        @foreach($repositories as $repo)
                            <option value="{{ $repo->id }}" @selected((string) request('repo') === (string) $repo->id)>{{ $repo->name }}</option>
                        @endforeach
                    </select>
                    <select name="state" class="select">
                        <option value="active" @selected(request('state', 'active') === 'active')>موجودة</option>
                        <option value="deleted" @selected(request('state') === 'deleted')>اتحذفت</option>
                    </select>
                    <x-button type="submit">فلتر</x-button>
                    @if(request()->hasAny(['q', 'author_user', 'repo', 'state']))
                        <x-button variant="ghost" :href="route('github.unmatched-branches')">مسح</x-button>
                    @endif
                </div>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead><tr><th>الريبو</th><th>البرانش</th><th>صاحبه</th><th>أول ظهور</th><th>آخر commit</th><th>الحالة</th><th></th></tr></thead>
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
                            <td><x-badge :variant="$branch->state->value === 'active' ? 'red' : 'gray'">{{ $branch->state->value === 'active' ? 'مخالفة' : 'اتحذفت' }}</x-badge></td>
                            <td>
                                @if($branch->state->value === 'active' && auth()->user()->hasPermission('github.branches.delete'))
                                    <form method="POST" action="{{ route('github.unmatched-branches.destroy', $branch) }}"
                                          onsubmit="const branchName = {{ Illuminate\Support\Js::from($branch->name) }}; const value = prompt('اكتب اسم البرانش للتأكيد:\n' + branchName); if (value === null) return false; this.elements.confirmation.value = value;">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="confirmation">
                                        <x-button variant="danger" size="sm" type="submit" :disabled="!$writeConfigured">احذف البرانش</x-button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="u-subtle">مفيش برانشات بالفلاتر دي.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $branches->links() }}
    </div>
@endsection
