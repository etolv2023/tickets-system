@if ($paginator->hasPages())
    <nav class="pagination" role="navigation" aria-label="تنقل بين الصفحات">
        <p class="pagination__summary">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} من {{ $paginator->total() }}
        </p>

        <div class="pagination__list">
            @if ($paginator->onFirstPage())
                <span class="pagination__link pagination__link--disabled" aria-hidden="true">السابق</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pagination__link" rel="prev">السابق</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination__gap">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <a href="{{ $url }}" class="pagination__link" aria-current="page">{{ $page }}</a>
                        @else
                            <a href="{{ $url }}" class="pagination__link">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pagination__link" rel="next">التالي</a>
            @else
                <span class="pagination__link pagination__link--disabled" aria-hidden="true">التالي</span>
            @endif
        </div>
    </nav>
@endif
