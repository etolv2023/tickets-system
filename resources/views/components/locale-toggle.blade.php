{{--
    Arabic / English switch.

    Shared by the topbar and the three screens outside the shell (login, the
    client portal, the install wizard), which have no topbar of their own.
    The button names the language it switches TO, in that language.
--}}

<form method="POST" action="{{ route('locale.update') }}" {{ $attributes->class('locale-toggle') }}>
    @csrf
    <input type="hidden" name="locale" value="{{ __('ui.language_code') }}">
    <button type="submit" class="topbar__btn" aria-label="{{ __('ui.language') }}" title="{{ __('ui.language') }}">
        <span class="u-mono">{{ app()->isLocale('ar') ? 'EN' : 'AR' }}</span>
    </button>
</form>
