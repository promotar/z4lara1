<x-frontend-layout>
    @php($searchUrl = route('blog.search'))
    <x-slot name="head">
        <!-- blog-seo:start -->
        <title>{{ $archiveTitle }} | Art INPA</title>
        <meta name="description" content="البحث في مقالات وأخبار Art INPA.">
        <meta name="robots" content="noindex, follow">
        <link rel="canonical" href="{{ $searchUrl }}">
        <!-- blog-seo:end -->
        <link rel="stylesheet" href="{{ route('blog.styles') }}">
    </x-slot>

    <section lang="ar" dir="rtl" style="max-width:1180px;margin:32px auto 0;padding:0 24px">
        <form action="{{ $searchUrl }}" method="GET" role="search" style="display:flex;gap:8px">
            <input name="q" value="{{ $term }}" type="search" placeholder="ابحث في المقالات" aria-label="ابحث في المقالات" style="min-width:0;flex:1;border:1px solid #dfcaca;border-radius:8px;padding:12px 14px">
            <button type="submit" style="border:0;border-radius:8px;background:#a90000;padding:0 20px;color:#fff;font-weight:700">بحث</button>
        </form>
    </section>

    @if($renderedTemplate)
        <div class="blog-selected-template" lang="ar" dir="rtl">{!! $renderedTemplate !!}</div>
        @include('blog::frontend.partials.pagination', ['posts' => $posts])
    @else
        <section class="blog-archive" lang="ar" dir="rtl"><div class="blog-shell"><header class="blog-archive-heading"><h1>{{ $archiveTitle }}</h1></header>@include('blog::frontend.partials.post-grid', ['posts' => $posts])</div></section>
    @endif
</x-frontend-layout>
