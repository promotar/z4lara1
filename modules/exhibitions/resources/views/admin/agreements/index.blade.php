@push('styles')<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-agreement-settings-2" data-exhibitions-stylesheet>@endpush
<x-app-layout>
<main class="z4-exhibitions-admin" data-exhibitions-agreements><div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading"><div><a class="z4-exhibitions-back" href="{{ route('admin.plugins.exhibitions.overview') }}">&larr; {{ __('exhibitions::ui.exhibitions_overview') }}</a><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_152ec56981') }}</span><h1>{{ __('exhibitions::ui.auto_b2a8c5b846') }}</h1><p>{{ __('exhibitions::ui.auto_85b6242ec1') }}</p></div>@if($defaultTemplate)<span class="z4-exhibitions-status is-ready">{{ __('exhibitions::ui.auto_57d7c98f2e') }}</span>@else<span class="z4-exhibitions-status">{{ __('exhibitions::ui.auto_74ca7e0e8b') }}</span>@endif</header>
    @if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="z4-exhibitions-alert is-error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="z4-agreement-settings-grid">
        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>01</span><div><h2>{{ __('exhibitions::ui.auto_c921b25726') }}</h2><p>{{ __('exhibitions::ui.auto_340d468661') }}</p></div></div>
            <div class="z4-agreement-version-list">
                @forelse($templates as $template)
                    <article class="{{ $template->is_default ? 'is-default' : '' }} {{ !$template->is_active ? 'is-archived' : '' }}">
                        <header><div><strong>{{ $template->title }}</strong><span>{{ __('exhibitions::ui.auto_d0e8f814a7') }}</span></div><div class="z4-agreement-badges">@if($template->is_default)<b>{{ __('exhibitions::ui.auto_7a1920d611') }}</b>@endif<b class="{{ $template->is_active ? 'is-active' : 'is-archived' }}">{{ $template->is_active ? __('exhibitions::ui.state_active') : __('exhibitions::ui.state_archived') }}</b></div></header>
                        <dl><div><dt>{{ __('exhibitions::ui.auto_9b9d8a976b') }}</dt><dd>{{ $template->published_at }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_b127158518') }}</dt><dd>{{ $template->assigned_count }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_382ab52293') }}</dt><dd>{{ $template->accepted_count }}</dd></div></dl>
                        <div class="z4-agreement-version-actions">
                            @if(!$template->is_default && $template->is_active)<form method="POST" action="{{ route('admin.plugins.exhibitions.agreements.default', $template->public_id) }}">@csrf<button type="submit">{{ __('exhibitions::ui.auto_630aa22e85') }}</button></form>@endif
                            @if(!$template->is_default)<form method="POST" action="{{ route('admin.plugins.exhibitions.agreements.active', $template->public_id) }}">@csrf<input type="hidden" name="active" value="{{ $template->is_active ? 0 : 1 }}"><button type="submit" class="is-secondary">{{ $template->is_active ? __('exhibitions::ui.archive') : __('exhibitions::ui.activate') }}</button></form>@endif
                        </div>
                    </article>
                @empty
                    <div class="z4-verification-waiting is-setup"><span>!</span><strong>{{ __('exhibitions::ui.auto_161731e377') }}</strong><p>{{ __('exhibitions::ui.auto_289396292d') }}</p></div>
                @endforelse
            </div>
            <x-platform-pagination :paginator="$templates" />
        </section>

        <section class="z4-exhibitions-panel">
            <div class="z4-exhibitions-section-title"><span>02</span><div><h2>{{ __('exhibitions::ui.auto_87169cb356') }}</h2><p>{{ __('exhibitions::ui.auto_8085f98f5f') }}</p></div></div>
            <div class="z4-verification-guidance is-waiting"><strong>{{ __('exhibitions::ui.auto_c84fc88eeb') }}</strong><p>{{ __('exhibitions::ui.auto_4baa7788c3') }}</p></div>
            <div class="z4-verification-guidance is-ready"><strong>{{ __('exhibitions::ui.auto_90f95ed83b') }}</strong><p>{{ __('exhibitions::ui.auto_b8428949c8') }} <a href="{{ route('admin.plugins.exhibitions.settings.commission') }}">{{ __('exhibitions::ui.auto_af6d70f526') }}</a>{{ __('exhibitions::ui.auto_2f55938c72') }}</p></div>
            <form method="POST" action="{{ route('admin.plugins.exhibitions.agreements.store') }}" class="z4-exhibitions-form">@csrf
                <div class="z4-exhibitions-fields"><label><span>{{ __('exhibitions::ui.auto_34b6cd7517') }}</span><input name="version" value="{{ old('version') }}" placeholder="2026.1" required></label><label><span>{{ __('exhibitions::ui.auto_b78a322350') }}</span><input name="title" value="{{ old('title') }}" required></label></div>
                <label><span>{{ __('exhibitions::ui.auto_8b853839f8') }}</span><textarea name="content" rows="18" minlength="100" required>{{ old('content') }}</textarea><small>{{ __('exhibitions::ui.auto_23faabee5d') }}</small></label>
                <label class="z4-exhibitions-consent"><input type="checkbox" name="make_default" value="1" checked><span>{{ __('exhibitions::ui.auto_d52cc2f7e1') }}</span></label>
                <button class="is-primary" type="submit">{{ __('exhibitions::ui.auto_ec2e464846') }}</button>
            </form>
        </section>
    </div>
</div></main>
</x-app-layout>
