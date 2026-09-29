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
                <x-button variant="secondary"><x-icon name="refresh" class="btn__icon" /> زامن دلوقتي</x-button>
            </form>
        </div>

        <x-card>
            <form method="GET" class="filters">
                <div class="filters__bar">
                    <input name="q" value="{{ request('q') }}" placeholder="اسم البرانش" class="input">
                    <input name="author" value="{{ request('author') }}" placeholder="GitHub login" class="input">
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
                </div>
            </form>
        </x-card>

        <x-card flush>
            <div class="table-wrap">
                <table class="table table--hover">
                    <thead><tr><th>الريبو</th><th>البرانش</th><th>صاحبه</th><th>أول ظهور</th><th>آخر commit</th><th>الحالة</th></tr></thead>
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
                        </tr>
                    @empty
                        <tr><td colspan="6" class="u-subtle">مفيش برانشات بالفلاتر دي.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        {{ $branches->links() }}
    </div>
@endsection
