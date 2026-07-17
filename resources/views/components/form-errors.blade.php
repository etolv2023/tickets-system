{{-- Summary at the top of a form. Individual messages still render under their
     own field via x-field. --}}
@if ($errors->any())
    <x-alert variant="error">
        <strong>مقدرتش أحفظ — راجع الحاجات دي:</strong>
        <ul class="alert__list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
