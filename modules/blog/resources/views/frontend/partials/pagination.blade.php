@if (method_exists($posts, 'links') && $posts->hasPages())
    @php
        $currentPage = $posts->currentPage();
        $lastPage = $posts->lastPage();
        $windowStart = max(1, $currentPage - 1);
        $windowEnd = min($lastPage, $currentPage + 1);
    @endphp

    <nav class="blog-pagination" aria-label="التنقل بين صفحات المقالات" lang="ar" dir="rtl">
        @if ($posts->onFirstPage())
            <span class="blog-pagination__item blog-pagination__item--disabled" aria-hidden="true">&rsaquo;</span>
        @else
            <a class="blog-pagination__item" href="{{ $posts->previousPageUrl() }}" rel="prev" aria-label="الصفحة السابقة">&rsaquo;</a>
        @endif
        @if ($windowStart > 1)
            <a class="blog-pagination__item" href="{{ $posts->url(1) }}">1</a>
            @if ($windowStart > 2)<span class="blog-pagination__item blog-pagination__item--dots">...</span>@endif
        @endif
        @for ($page = $windowStart; $page <= $windowEnd; $page++)
            @if ($page === $currentPage)
                <span class="blog-pagination__item blog-pagination__item--active" aria-current="page">{{ $page }}</span>
            @else
                <a class="blog-pagination__item" href="{{ $posts->url($page) }}">{{ $page }}</a>
            @endif
        @endfor
        @if ($windowEnd < $lastPage)
            @if ($windowEnd < $lastPage - 1)<span class="blog-pagination__item blog-pagination__item--dots">...</span>@endif
            <a class="blog-pagination__item" href="{{ $posts->url($lastPage) }}">{{ $lastPage }}</a>
        @endif
        @if ($posts->hasMorePages())
            <a class="blog-pagination__item" href="{{ $posts->nextPageUrl() }}" rel="next" aria-label="الصفحة التالية">&lsaquo;</a>
        @else
            <span class="blog-pagination__item blog-pagination__item--disabled" aria-hidden="true">&lsaquo;</span>
        @endif
    </nav>
@endif
