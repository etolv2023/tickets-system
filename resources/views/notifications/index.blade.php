@extends('layouts.app')

@section('title', 'الإشعارات')

@section('content')
    <div class="page page--medium">
        <div class="page__head">
            <div>
                <h1 class="page-title">الإشعارات</h1>
                <p class="page-subtitle">
                    كل حاجة بتحصل على تذكرة انت طرف فيها — سواء متعملك أساين، أو بتتابعها،
                    أو ماسك صب تاسك منها.
                </p>
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

        {{-- The permission prompt only opens from a real user gesture, so this
             is a button rather than something that fires on load. It sits in
             the flow of the page instead of the header because a ghost button
             up there read as body text and nobody found it. Gone once granted:
             a banner asking for something you already gave is noise. --}}
        <div x-data="notifyPermission(@js(config('webpush.public_key')))" x-cloak
             x-show="state === 'default' || state === 'denied'"
             class="notify-prompt">
            <div class="notify-prompt__text">
                <p class="notify-prompt__title" x-text="label">فعّل إشعارات المتصفح</p>
                <p class="notify-prompt__hint" x-text="hint"></p>
            </div>

            <x-button variant="primary" size="sm" type="button"
                      x-show="state === 'default'" @click="request()">فعّل</x-button>
        </div>

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

        <x-card flush>
            <div class="notif-list">
                @php $lastDay = null; @endphp

                @forelse ($notifications as $notification)
                    @php
                        $data = $notification->data;
                        $day = $notification->created_at->translatedFormat('l j F Y');
                    @endphp

                    {{-- A date heading whenever the day changes: "which day was
                         that" is the question people bring to this screen. --}}
                    @if ($day !== $lastDay)
                        <p class="notif-day">{{ $day }}</p>
                        @php $lastDay = $day; @endphp
                    @endif

                    <a class="notif {{ $notification->read_at ? '' : 'notif--unread' }}"
                       href="{{ route('notifications.read', $notification->id) }}">
                        <span class="notif__icon notif__icon--{{ $data['variant'] ?? 'slate' }}">
                            <x-icon :name="$data['icon'] ?? 'bell'" size="0.95em" />
                        </span>

                        <span class="notif__body">
                            <span class="notif__head">
                                <span class="notif__label">{{ $data['label'] ?? '' }}</span>
                                @unless ($notification->read_at)
                                    <span class="notif__new">جديد</span>
                                @endunless
                            </span>

                            <span class="notif__text">{{ $data['message'] ?? '' }}</span>

                            <span class="notif__meta">
                                <span class="u-mono u-ltr">{{ $data['ticket_number'] ?? '' }}</span>
                                <span aria-hidden="true">·</span>
                                <time datetime="{{ $notification->created_at->toIso8601String() }}">
                                    {{ $notification->created_at->diffForHumans() }}
                                </time>
                            </span>
                        </span>
                    </a>
                @empty
                    <p class="card__empty">
                        {{ array_filter($filters) ? 'مفيش إشعارات مطابقة للفلتر.' : 'مفيش إشعارات لسه.' }}
                    </p>
                @endforelse
            </div>
        </x-card>

        {{ $notifications->links() }}
    </div>
@endsection
