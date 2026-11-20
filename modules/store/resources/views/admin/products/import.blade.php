<x-app-layout>
<main class="z4-store-admin z4-store-catalog-transfer-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_c0d62787e6') }}</span>
                <h1>{{ __('store::ui.auto_4bfc4a7632') }}</h1>
                <p>{{ __('store::ui.auto_2db256c8d1') }}</p>
            </div>
            <div class="z4-store-heading-actions">
                <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.products.export') }}">{{ __('store::ui.auto_180569926f') }}</a>
                <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.products.index') }}">{{ __('store::ui.auto_60ec65bc05') }}</a>
            </div>
        </header>

        @if(session('catalog_import_summary'))
            @php($summary = session('catalog_import_summary'))
            <section class="z4-store-stat-grid" aria-label="{{ __('store::ui.auto_8232c09fd6') }}">
                <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-purple">＋</span><div><small>{{ __('store::ui.auto_0eceeb4586') }}</small><strong>{{ $summary['created'] }}</strong><span>{{ __('store::ui.auto_9ff0635f57') }}</span></div></article>
                <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-blue">↻</span><div><small>{{ __('store::ui.auto_ff0a3b7f3d') }}</small><strong>{{ $summary['updated'] }}</strong><span>{{ __('store::ui.auto_11cd0017ce') }}</span></div></article>
                <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-orange">−</span><div><small>{{ __('store::ui.auto_d9c8f18797') }}</small><strong>{{ $summary['skipped'] }}</strong><span>{{ __('store::ui.auto_ecbefb1d2c') }}</span></div></article>
                <article class="z4-store-stat-card"><span class="z4-store-stat-icon is-green">▧</span><div><small>{{ __('store::ui.auto_8ec5240b60') }}</small><strong>{{ $summary['images_imported'] }}</strong><span>{{ __('store::ui.auto_abed4feb42') }}</span></div></article>
            </section>
            @if(($summary['failed'] ?? 0) > 0 || ($summary['images_failed'] ?? 0) > 0)
                <section class="z4-store-alert z4-store-alert-warning" role="alert">
                    <strong>{{ __('store::ui.auto_6d8cae48e4') }}</strong>
                    @if(!empty($summary['errors']))
                        <ul>@foreach($summary['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                    @endif
                </section>
            @endif
        @endif

        <div class="z4-store-catalog-transfer-grid">
            <section class="z4-store-form-card">
                <header class="z4-store-section-heading"><span>⇧</span><div><h2>{{ __('store::ui.auto_d46c4ae6be') }}</h2><p>{{ __('store::ui.auto_ebe3f6ca31') }}</p></div></header>
                <form method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.store.products.import.store') }}" class="z4-store-catalog-transfer-form" data-dirty-form>
                    @csrf
                    <label class="is-wide">
                        <span>{{ __('store::ui.auto_0072cbeb5f') }} <b>*</b></span>
                        <input class="z4-store-input" type="file" name="catalog_csv" accept=".csv,text/csv" required>
                        <small>{{ __('store::ui.auto_f106d9accc') }}</small>
                    </label>
                    <label class="is-wide">
                        <span>{{ __('store::ui.auto_ad81bdaef3') }}</span>
                        <select class="z4-store-select" name="existing_behavior" required>
                            <option value="skip" @selected(old('existing_behavior', 'skip') === 'skip')>{{ __('store::ui.auto_3978a7b7cf') }}</option>
                            @can('store.products.update')<option value="update" @selected(old('existing_behavior') === 'update')>{{ __('store::ui.auto_6a39d85c29') }}</option>@endcan
                        </select>
                    </label>
                    <label class="z4-store-transfer-check">
                        <input type="hidden" name="download_images" value="0">
                        <input type="checkbox" name="download_images" value="1" @checked(old('download_images', true))>
                        <span><strong>{{ __('store::ui.auto_e7b6c1bb0a') }}</strong><small>{{ __('store::ui.auto_8663304bf4') }}</small></span>
                    </label>
                    <label class="z4-store-transfer-check">
                        <input type="hidden" name="preserve_status" value="0">
                        <input type="checkbox" name="preserve_status" value="1" @checked(old('preserve_status', false))>
                        <span><strong>{{ __('store::ui.auto_a8d4030d94') }}</strong><small>{{ __('store::ui.auto_18dc83e9a7') }}</small></span>
                    </label>
                    <button class="z4-store-btn z4-store-btn-primary" type="submit" data-submit-button>{{ __('store::ui.auto_4bfc4a7632') }}</button>
                </form>
            </section>

            <aside class="z4-store-form-card z4-store-transfer-notes">
                <header class="z4-store-section-heading"><span>✓</span><div><h2>{{ __('store::ui.auto_f9df996d94') }}</h2><p>{{ __('store::ui.auto_533c577192') }}</p></div></header>
                <ul>
                    <li>{{ __('store::ui.auto_458d796375') }}</li>
                    <li>{{ __('store::ui.auto_fb05b345b9') }}</li>
                    <li>{{ __('store::ui.auto_abaaf91f73') }}</li>
                    <li>{{ __('store::ui.auto_d59d276a49') }}</li>
                </ul>
                <p>{{ __('store::ui.auto_b33ab4968a') }}</p>
            </aside>
        </div>
    </div>
</main>
</x-app-layout>
