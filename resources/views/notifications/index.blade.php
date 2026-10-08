@extends('layouts.app')

@section('title', 'الإشعارات')

@section('content')
    <div class="page notifications-page">
        <div class="page__head">
            <div>
                <h1 class="page-title">الإشعارات</h1>
                <p class="page-subtitle">
                    كل حاجة بتحصل على تذكرة انت طرف فيها — سواء متعملك أساين، أو بتتابعها،
                    أو ماسك صب تاسك منها.
                </p>
                <div class="notifications-page__summary">
                    <span><strong>{{ $notifications->total() }}</strong> إشعار</span>
                    <span @class(['notifications-page__unread', 'notifications-page__unread--empty' => $unread === 0])>
                        <strong>{{ $unread }}</strong> غير مقروء
                    </span>
                </div>
            </div>
            @if ($unread > 0)
                <div class="page__actions">
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <x-button variant="secondary">علّم الكل مقروء ({{ $unread }})</x-button>
                    </form>
                </div>
            @endif
        </div>

        {{-- ★★★★★ (2026-07-21): the permission prompt moved to the app shell
             (layouts/app.blade.php) so it follows you on every page, not just
             this one — see notify-banner.blade.php. --}}

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        {{-- They pile up fast, so the list is searchable and filterable rather
             than something you scroll until you give up. --}}
        <form method="GET" action="{{ route('notifications.index') }}" class="filters">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="input filters__search"
                   placeholder="دوّر في نص الإشعار أو رقم التذكرة…" aria-label="بحث">

            <select name="group" class="select filters__select" aria-label="النوع">
                <option value="">كل الأنواع</option>
                @foreach ($groups as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['group'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="state" class="select filters__select" aria-label="الحالة">
                <option value="">المقروء وغير المقروء</option>
                <option value="unread" @selected(($filters['state'] ?? '') === 'unread')>غير المقروء بس</option>
                <option value="read" @selected(($filters['state'] ?? '') === 'read')>المقروء بس</option>
            </select>

            <x-button variant="secondary" size="sm">فلترة</x-button>

            @if (array_filter($filters))
                <a class="btn btn--ghost btn--sm" href="{{ route('notifications.index') }}">مسح</a>
            @endif
        </form>

        <x-card flush class="notifications-card">
            <div class="notif-list">
                @forelse ($notifications->getCollection()->groupBy(fn ($item) => $item->created_at->toDateString()) as $dayItems)
                    <section class="notif-group">
                        <header class="notif-day">
                            <span>{{ $dayItems->first()->created_at->translatedFormat('l j F Y') }}</span>
                            <span>{{ $dayItems->count() }} إشعار</span>
                        </header>

                        @foreach ($dayItems as $notification)
                            @php $data = $notification->data; @endphp
                            <a class="notif {{ $notification->read_at ? '' : 'notif--unread' }}"
                               href="{{ route('notifications.read', $notification->id) }}">
                                <span class="notif__icon notif__icon--{{ $data['variant'] ?? 'slate' }}">
                                    <x-icon :name="$data['icon'] ?? 'bell'" size="1em" />
                                </span>

                                <span class="notif__body">
                                    <span class="notif__head">
                                        <span class="notif__label">{{ $data['label'] ?? '' }}</span>
                                        @unless ($notification->read_at)<span class="notif__new">جديد</span>@endunless
                                    </span>
                                    <span class="notif__text">{{ $data['message'] ?? '' }}</span>
                                    <span class="notif__meta">
                                        <span class="u-mono u-ltr">{{ $data['ticket_number'] ?? '' }}</span>
                                        <span aria-hidden="true">·</span>
                                        <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                                    </span>
                                </span>
                                <x-icon name="chevron-left" class="notif__arrow" />
                            </a>
                        @endforeach
                    </section>
                @empty
                    <p class="card__empty">
                        {{ array_filter($filters) ? 'مفيش إشعارات مطابقة للفلتر.' : 'مفيش إشعارات لسه.' }}
                    </p>
                @endforelse
            </div>

            <div class="notif-pagination">
                {{ $notifications->links() }}
            </div>
        </x-card>
    </div>
@endsection
