@extends('certificates::public.layout')
@section('title', __('certificates::public.title'))
@section('content')
    <style>
        .certificates-public .certificate-search .certificate-search__divider{margin:28px 0 22px;padding-top:22px;border-top:1px dashed #d8c9ea}
        .certificates-public .certificate-search .certificate-search__or{display:block;color:var(--art-color-accent, #624397);font-weight:800}
        .certificates-public .certificate-search button[type=submit]{margin-top:30px}
    </style>
    <section class="search-panel" aria-labelledby="certificate-search-title">
        <div class="certificate-heading">
            <span class="certificate-emblem" aria-hidden="true"><span class="certificate-symbol"></span></span>
            <h1 id="certificate-search-title">{{ __('certificates::public.title') }}</h1>
        </div>
        <p class="certificate-intro">{{ __('certificates::public.intro') }}</p>
        <form method="POST" action="{{ route('certificates.lookup') }}" class="certificate-search">
            @csrf
            <label for="certificate-number">{{ __('certificates::public.number_label') }}</label>
            <input type="text" id="certificate-number" name="slug" maxlength="190" autocomplete="off" autocapitalize="none" spellcheck="false" dir="ltr" aria-describedby="certificate-help">
            <p id="certificate-help" class="certificate-help">{{ __('certificates::public.number_help') }}</p>

            <span class="certificate-search__or certificate-search__divider">{{ __('certificates::public.or') }}</span>
            <label for="certificate-name">{{ __('certificates::public.name_label') }}</label>
            <input type="text" id="certificate-name" name="name" maxlength="255" autocomplete="off" dir="auto">

            <button type="submit">{{ __('certificates::public.submit') }}</button>
        </form>
    </section>
    <div class="certificate-home-link"><a href="{{ url('/') }}">{{ __('certificates::public.home_link') }}</a></div>
@endsection
