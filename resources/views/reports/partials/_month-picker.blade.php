<form method="GET" action="{{ route($route, request()->route('user') ? ['user' => request()->route('user')] : []) }}" class="filters">
    <select name="period" class="select filters__select" onchange="this.form.submit()">
        @foreach ($months as $value => $label)
            <option value="{{ $value }}" @selected($period === $value)>{{ __($label) }}</option>
        @endforeach
    </select>
    <noscript><x-button variant="secondary">{{ __('عرض') }}</x-button></noscript>
</form>
