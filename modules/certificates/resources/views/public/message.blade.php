@extends('certificates::public.layout')
@section('title', $title)
@section('content')
    <section class="search-panel certificate-message"><h1>{{ $title }}</h1><p role="status">{{ $message }}</p><a class="back-link" href="{{ route('certificates.search') }}">{{ __('certificates::public.back_search') }}</a></section>
@endsection
