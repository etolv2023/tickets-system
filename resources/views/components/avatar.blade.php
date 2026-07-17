@props([
    'user' => null,
    /* sm | null (default) | lg | xl */
    'size' => null,
])

@php
    $label = $user?->name ?? 'غير معروف';
@endphp

<span
    {{ $attributes->class([
        'avatar',
        'avatar--' . $size => $size,
        'avatar--inactive' => $user && ! $user->is_active,
    ]) }}
    title="{{ $label }}"
>
    @if ($user?->avatar_path)
        <img class="avatar__img" src="{{ Storage::url($user->avatar_path) }}" alt="{{ $label }}">
    @else
        {{ $user?->initials() ?? '؟' }}
    @endif
</span>
