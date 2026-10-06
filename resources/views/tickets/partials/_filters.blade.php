<form method="GET" action="{{ route('tickets.index') }}" class="filters">
    @include('tickets.partials._filters-basic')
    @include('tickets.partials._filters-advanced')
</form>
