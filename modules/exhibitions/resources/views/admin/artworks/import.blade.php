@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artwork-import" data-exhibitions-stylesheet>
@endpush
<x-app-layout>
<main class="z4-exhibitions-admin">
<div class="z4-exhibitions-container z4-exhibitions-form-container">
    <header class="z4-exhibitions-heading">
        <div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_98f4f2a5c2') }}</span><h1>{{ __('exhibitions::ui.auto_377ebaa53f') }}</h1><p>{{ __('exhibitions::ui.import_subtitle') }}</p></div>
        <div class="z4-workspace-heading-actions"><a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.artworks.export') }}">{{ __('exhibitions::ui.auto_5db5c078d2') }}</a><a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.workspace',['area'=>'artworks','tab'=>'all']) }}">{{ __('exhibitions::ui.auto_352c531b9b') }}</a></div>
    </header>
    @if(session('status'))<div class="z4-exhibitions-alert is-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(session('import_report') && session('import_report.failed'))
        <div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_b17138a3ff') }}</strong><ul>@foreach(session('import_report.errors',[]) as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form class="z4-exhibitions-form" method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.exhibitions.artworks.import.store') }}">
        @csrf
        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>CSV</span><div><h2>{{ __('exhibitions::ui.auto_9dfca4b84d') }}</h2><p>{{ __('exhibitions::ui.auto_6bd5396d1d') }}</p></div></div>
            <div class="z4-exhibitions-fields">
                <label>{{ __('exhibitions::ui.auto_0072cbeb5f') }} <b>*</b><input type="file" name="file" accept=".csv,text/csv,text/plain" required></label>
                <label>{{ __('exhibitions::ui.auto_ab0e1e035c') }}<select name="fallback_artist_user_id"><option value="">{{ __('exhibitions::ui.auto_d88f1912d6') }}</option>@foreach($artists as $artist)<option value="{{ $artist->core_user_id }}" @selected((string)old('fallback_artist_user_id')===(string)$artist->core_user_id)>{{ $artist->display_name }} — {{ $artist->email }}</option>@endforeach</select><small>{{ __('exhibitions::ui.auto_de418cc7a6') }}</small></label>
            </div>
            <div class="z4-import-contract"><strong>{{ __('exhibitions::ui.auto_9b284baa3f') }}</strong><code>title</code><strong>{{ __('exhibitions::ui.auto_2d75a67fee') }}</strong><code>artist_email</code><strong>{{ __('exhibitions::ui.auto_4b0f2ed65c') }}</strong><code>image_urls</code><small>{{ __('exhibitions::ui.auto_d4265af765') }}</small></div>
        </section>
        <div class="z4-exhibitions-actions"><a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.workspace',['area'=>'artworks','tab'=>'all']) }}">{{ __('exhibitions::ui.auto_ea4788705e') }}</a><button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_73048cde2b') }}</button></div>
    </form>
</div>
</main>
</x-app-layout>
