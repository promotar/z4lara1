@php($blogCssPath = base_path('modules/blog/resources/css/blog.css'))
<link rel="stylesheet" href="{{ route('blog.styles') }}?v={{ is_file($blogCssPath) ? filemtime($blogCssPath) : '1' }}">
