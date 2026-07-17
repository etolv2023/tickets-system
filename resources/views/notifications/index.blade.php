@extends('layouts.app')

@section('title', 'الإشعارات')

@section('content')
    <div class="page page--narrow">
        <div class="page__head">
            <div>
                <h1 class="page-title">الإشعارات</h1>
                <p class="page-subtitle">{{ $unread }} غير مقروء.</p>
            </div>
            @if ($unread > 0)
                <div class="page__actions">
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <x-button variant="ghost">علّم الكل مقروء</x-button>
                    </form>
                </div>
            @endif
        </div>

        @if (session('status'))
            <x-alert variant="success">{{ session('status') }}</x-alert>
        @endif

        <x-card flush>
            <div class="subtasks">
                @forelse ($notifications as $notification)
                    <a class="notif {{ $notification->read_at ? '' : 'notif--unread' }}"
                       href="{{ route('notifications.read', $notification->id) }}">
                        <span class="notif__dot"></span>

                        <span class="notif__body">
                            <span class="notif__text">{{ $notification->data['message'] ?? '' }}</span>
                            <span class="notif__meta">
                                <span class="u-mono u-ltr">{{ $notification->data['ticket_number'] ?? '' }}</span>
                                · {{ $notification->created_at->diffForHumans() }}
                            </span>
                        </span>
                    </a>
                @empty
                    <p class="card__empty">مفيش إشعارات.</p>
                @endforelse
            </div>
        </x-card>

        {{ $notifications->links() }}
    </div>
@endsection
