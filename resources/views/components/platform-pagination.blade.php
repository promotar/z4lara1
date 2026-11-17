@props([
    'paginator',
    'pageName' => null,
])

@php
    $pageName = $pageName ?: $paginator->getPageName();
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $firstWindowPage = max(1, $currentPage - 2);
    $lastWindowPage = min($lastPage, $currentPage + 2);
    $queryInputs = [];
    $flattenQuery = function (array $values, string $prefix = '') use (&$flattenQuery, &$queryInputs, $pageName): void {
        foreach ($values as $key => $value) {
            if ($prefix === '' && (string) $key === $pageName) {
                continue;
            }

            $name = $prefix === '' ? (string) $key : $prefix.'['.$key.']';
            if (is_array($value)) {
                $flattenQuery($value, $name);
            } elseif (is_scalar($value) && $value !== '') {
                $queryInputs[] = [$name, (string) $value];
            }
        }
    };
    $flattenQuery(request()->query());
@endphp

@if ($lastPage > 1)
    <nav class="platform-pagination" aria-label="Pagination">
        <div class="platform-pagination__summary">
            {{ number_format($paginator->firstItem() ?? 0) }}–{{ number_format($paginator->lastItem() ?? 0) }}
            of {{ number_format($paginator->total()) }}
        </div>

        <div class="platform-pagination__links">
            <a href="{{ $paginator->url(1) }}" class="platform-pagination__link" aria-label="First page" @if($currentPage === 1) aria-disabled="true" tabindex="-1" @endif>First</a>
            <a href="{{ $paginator->previousPageUrl() ?: $paginator->url(1) }}" class="platform-pagination__link" rel="prev" aria-label="Previous page" @if($paginator->onFirstPage()) aria-disabled="true" tabindex="-1" @endif>Previous</a>

            @if ($firstWindowPage > 1)<span class="platform-pagination__ellipsis" aria-hidden="true">…</span>@endif
            @foreach ($paginator->getUrlRange($firstWindowPage, $lastWindowPage) as $page => $url)
                <a href="{{ $url }}" class="platform-pagination__link {{ $page === $currentPage ? 'is-current' : '' }}" @if($page === $currentPage) aria-current="page" @endif>{{ $page }}</a>
            @endforeach
            @if ($lastWindowPage < $lastPage)<span class="platform-pagination__ellipsis" aria-hidden="true">…</span>@endif

            <a href="{{ $paginator->nextPageUrl() ?: $paginator->url($lastPage) }}" class="platform-pagination__link" rel="next" aria-label="Next page" @if(! $paginator->hasMorePages()) aria-disabled="true" tabindex="-1" @endif>Next</a>
            <a href="{{ $paginator->url($lastPage) }}" class="platform-pagination__link" aria-label="Last page" @if($currentPage === $lastPage) aria-disabled="true" tabindex="-1" @endif>Last</a>
        </div>

        <form method="GET" action="{{ url()->current() }}" class="platform-pagination__jump">
            @foreach ($queryInputs as [$name, $value])
                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
            @endforeach
            <label for="pagination-{{ $pageName }}-{{ $paginator->firstItem() }}">Page</label>
            <input id="pagination-{{ $pageName }}-{{ $paginator->firstItem() }}" type="number" name="{{ $pageName }}" value="{{ $currentPage }}" min="1" max="{{ $lastPage }}" inputmode="numeric" required>
            <button type="submit">Go</button>
        </form>
    </nav>
@endif
