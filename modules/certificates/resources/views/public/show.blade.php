@extends('certificates::public.layout')
@section('title', $certificate->name)
@section('content')
    <article class="certificate-display">
        <header>
            <h1>{{ $certificate->name }}</h1>
            <dl class="certificate-meta">
                <div class="certificate-meta__item"><dt>{{ __('certificates::public.number_field') }}</dt><dd dir="ltr">{{ $certificate->slug }}</dd></div>
                <div class="certificate-meta__item"><dt>{{ __('certificates::public.date_field') }}</dt><dd><time datetime="{{ $certificate->date->toDateString() }}">{{ $certificate->date->format('Y-m-d') }}</time></dd></div>
            </dl>
        </header>
        <img src="{{ $imageUrl }}" alt="{{ __('certificates::public.image_alt', ['name' => $certificate->name]) }}" class="certificate-original" decoding="async">
        <a class="back-link" href="{{ route('certificates.search') }}">{{ __('certificates::public.title') }}</a>
    </article>
@endsection
