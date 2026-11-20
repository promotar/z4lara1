@extends('certificates::frontend.layout')
@section('title', $certificate->exists ? 'Edit Certificate' : 'Issue Certificate')
@section('content')
    <div class="manage-panel">
        <h1>{{ $certificate->exists ? 'Edit Certificate' : 'Issue Certificate' }}</h1>

        @if(session('status'))<div class="manage-notice">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="manage-notice is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" enctype="multipart/form-data" action="{{ $certificate->exists ? route('certificates.manage.update', $certificate, false) : route('certificates.manage.store', [], false) }}">
            @csrf
            @if($certificate->exists) @method('PUT') @endif

            <div class="field"><label for="certificate-name">{{ __('certificates::ui.name') }}</label><input id="certificate-name" type="text" name="name" required maxlength="255" value="{{ old('name', $certificate->name) }}"></div>
            <div class="field"><label for="certificate-date">{{ __('certificates::ui.date') }}</label><input id="certificate-date" type="date" name="date" required value="{{ old('date', $certificate->date?->toDateString() ?? now()->toDateString()) }}"></div>
            <div class="field"><label for="certificate-slug">{{ __('certificates::ui.slug') }}</label><input id="certificate-slug" type="text" name="slug" required maxlength="190" value="{{ old('slug', $certificate->slug) }}" autocomplete="off" dir="ltr"></div>
            <div class="field">
                <label for="certificate-image">{{ __('certificates::ui.image') }}</label>
                <input id="certificate-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" @required(! $certificate->exists)>
                @if($certificate->exists)
                    <img class="manage-preview" src="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('certificates.image', now()->addMinutes(10), ['slug' => $certificate->slug]) }}" alt="{{ __('certificates::ui.certificate_image_alt', ['name' => $certificate->name]) }}">
                @endif
            </div>

            <div class="manage-actions-row">
                <button type="submit">{{ __('certificates::ui.save') }}</button>
                <a class="manage-link" href="{{ route('certificates.manage.index', [], false) }}">{{ __('certificates::ui.back_to_certificates') }}</a>
            </div>
        </form>
    </div>
@endsection
