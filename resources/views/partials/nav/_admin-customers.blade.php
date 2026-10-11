{{-- Everything about who the tickets are for. --}}
@can('companies.manage')
    <a href="{{ route('admin.companies.index') }}" class="nav__link" title="{{ __('ui.nav.companies') }}"
       @if (request()->routeIs('admin.companies.*')) aria-current="page" @endif>
        <x-icon name="building" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.companies') }}</span>
    </a>

    <a href="{{ route('admin.contacts.index') }}" class="nav__link" title="{{ __('ui.nav.contacts') }}"
       @if (request()->routeIs('admin.contacts.*')) aria-current="page" @endif>
        <x-icon name="contact" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.contacts') }}</span>
    </a>
@endcan

@canany(['companies.manage', 'users.manage'])
    <a href="{{ route('admin.import.index') }}" class="nav__link" title="{{ __('ui.nav.excel_import') }}"
       @if (request()->routeIs('admin.import.*')) aria-current="page" @endif>
        <x-icon name="import" class="nav__icon" />
        <span class="nav__label">{{ __('ui.nav.excel_import') }}</span>
    </a>
@endcanany
