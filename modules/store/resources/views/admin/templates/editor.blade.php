<x-app-layout>
@php
    $isEdit = $template->exists;
    $isSystem = (bool) ($isSystem ?? false);
    $action = $isEdit
        ? route('admin.plugins.store.templates.update', $template, false)
        : route('admin.plugins.store.templates.store', [], false);
@endphp
<main class="z4-store-admin z4-store-template-studio" data-store-template-studio>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_c619f1a2b7') }}</span>
                <h1>{{ __('store::ui.auto_c5cdb3cd64') }}</h1>
                <p>{{ __('store::ui.auto_206cb6b282') }}</p>
            </div>
            <a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.templates.index', [], false) }}">{{ __('store::ui.auto_7a1cc67a97') }}</a>
        </header>

        @if(session('status'))<div class="z4-store-template-notice">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="z4-store-template-errors"><strong>{{ __('store::ui.auto_533153ab31') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="z4-store-template-layout">
            <aside class="z4-store-template-library">
                <header><strong>{{ __('store::ui.auto_6605cbc5b7') }}</strong><span>{{ $templates->total() }}</span></header>
                <div class="z4-store-template-list">
                    @forelse($templates as $item)
                        <a class="z4-store-template-item @if($isEdit && $item->id === $template->id) is-active @endif" href="{{ route('admin.plugins.store.templates.edit', $item, false) }}">
                            @if($item->preview_image)<img src="{{ $item->preview_image }}" alt="">@else<span class="z4-store-template-thumb">T</span>@endif
                            <span><strong>{{ $item->name }}</strong><small>{{ $item->category }} · {{ $item->status === 'active' ? 'Published' : 'Draft' }}{{ $item->isSystem() ? ' · Protected' : '' }}</small></span>
                        </a>
                    @empty
                        <p class="z4-store-template-empty">{{ __('store::ui.auto_0444d3a361') }}</p>
                    @endforelse
                    <x-platform-pagination :paginator="$templates" page-name="templates_page" />
                </div>
            </aside>

            <form class="z4-store-template-workspace" method="post" action="{{ $action }}" data-template-form>
                @csrf
                @if($isEdit) @method('PUT') @endif

                @if($isSystem)
                    <div class="z4-store-template-notice"><strong>{{ __('store::ui.auto_46a95a4606') }}</strong> {{ __('store::ui.auto_a2376f2e2a') }}</div>
                @endif

                <section class="z4-store-template-card">
                    <header><div><h2>{{ __('store::ui.auto_3923bfba54') }}</h2><p>{{ __('store::ui.auto_259d5e3622') }}</p></div></header>
                    <div class="z4-store-template-meta">
                        <label><span>{{ __('store::ui.auto_49ee308734') }}</span><input class="z4-store-input" name="name" value="{{ old('name', $template->name) }}" required @readonly($isSystem)></label>
                        <label><span>{{ __('store::ui.auto_0c90858852') }}</span><input class="z4-store-input" name="slug" value="{{ old('slug', $template->slug) }}" placeholder="{{ __('store::ui.auto_b45a14706f') }}" @readonly($isSystem)></label>
                        <label><span>{{ __('store::ui.auto_3adbdb3ac0') }}</span><select class="z4-store-select" name="category" @disabled($isSystem)>@foreach($categories as $value => $label)<option value="{{ $value }}" @selected(old('category', $template->category ?: 'collection') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label><span>{{ __('store::ui.auto_ec53a8c4f0') }}</span><select class="z4-store-select" name="status" @disabled($isSystem)><option value="draft" @selected(old('status', $template->status ?: 'draft') === 'draft')>{{ __('store::ui.auto_f03ab16cd5') }}</option><option value="active" @selected(old('status', $template->status) === 'active')>{{ __('store::ui.auto_9b9d8a976b') }}</option></select></label>
                        <label class="is-wide"><span>{{ __('store::ui.auto_4a1f524f24') }} <small>{{ __('store::ui.auto_f53d1cd25e') }}</small></span><input class="z4-store-input" name="preview_image" value="{{ old('preview_image', $template->preview_image) }}" placeholder="{{ __('store::ui.auto_076942b4ef') }}" @readonly($isSystem)></label>
                    </div>
                </section>

                <section class="z4-store-template-card">
                    <header><div><h2>{{ __('store::ui.auto_16adcc468e') }}</h2><p>{{ __('store::ui.auto_1852f45be6') }}</p></div></header>
                    <div class="z4-store-template-tabs" role="tablist" aria-label="{{ __('store::ui.auto_16adcc468e') }}">
                        <button class="is-active" type="button" role="tab" aria-selected="true" data-template-tab="html">HTML</button>
                        <button type="button" role="tab" aria-selected="false" data-template-tab="css">CSS</button>
                        <button type="button" role="tab" aria-selected="false" data-template-tab="js">{{ __('store::ui.auto_686155af75') }}</button>
                        <button type="button" role="tab" aria-selected="false" data-template-tab="variables">{{ __('store::ui.auto_f9491b1143') }}</button>
                    </div>
                    <div class="z4-store-template-panel is-active" data-template-panel="html"><textarea name="html_code" spellcheck="false" @readonly($isSystem)>{{ old('html_code', $template->html_code) }}</textarea><footer><span>HTML</span><span data-line-count="html">{{ __('store::ui.auto_288cf0de04') }}</span></footer></div>
                    <div class="z4-store-template-panel" data-template-panel="css"><textarea name="css_code" spellcheck="false" @readonly($isSystem)>{{ old('css_code', $template->css_code) }}</textarea><footer><span>CSS</span><span data-line-count="css">{{ __('store::ui.auto_288cf0de04') }}</span></footer></div>
                    <div class="z4-store-template-panel" data-template-panel="js"><textarea name="js_code" spellcheck="false" @readonly($isSystem)>{{ old('js_code', $template->js_code) }}</textarea><footer><span>{{ __('store::ui.auto_270ff22f11') }}</span><span data-line-count="js">{{ __('store::ui.auto_288cf0de04') }}</span></footer></div>
                    <div class="z4-store-template-panel" data-template-panel="variables">
                        <div class="z4-store-template-variables">
                            <p>{{ __('store::ui.auto_d5efeb55c4') }}</p>
                            <div class="z4-store-template-variable-grid">
                                @foreach($tokenGroups as $group => $entries)
                                    <section><h3>{{ $group }}</h3>@foreach($entries as $entry)<button type="button" data-template-token="{{ $entry['token'] }}" @disabled($isSystem)><code>{{ $entry['token'] }}</code><span>{{ $entry['description'] }}</span></button>@endforeach</section>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                <section class="z4-store-template-card">
                    <header><div><h2>{{ __('store::ui.auto_7d0b61c6d3') }}</h2><p>{{ __('store::ui.auto_54346a778c') }}</p></div><div class="z4-store-template-preview-tools"><button class="is-active" type="button" data-preview-size="desktop">{{ __('store::ui.auto_2310408a63') }}</button><button type="button" data-preview-size="tablet">{{ __('store::ui.auto_36384a9ea3') }}</button><button type="button" data-preview-size="mobile">{{ __('store::ui.auto_87d17f4624') }}</button></div></header>
                    <div class="z4-store-template-preview-stage"><iframe title="{{ __('store::ui.auto_367e966c43') }}" scrolling="no" sandbox="allow-same-origin allow-scripts" data-template-preview></iframe></div>
                    <footer class="z4-store-template-actions">
                        <div>@if($isEdit && !$isSystem)<button class="z4-store-btn z4-store-btn-ghost is-danger" type="button" data-delete-template>{{ __('store::ui.auto_f2a6c498fb') }}</button>@endif</div>
                        <div>@if($isEdit)<button class="z4-store-btn z4-store-btn-ghost" type="submit" form="copy-store-template">{{ __('store::ui.auto_dd70aae7d5') }}</button>@endif<button class="z4-store-btn z4-store-btn-ghost" type="button" data-refresh-template>{{ __('store::ui.auto_fdee486b38') }}</button>@if(!$isSystem)<button class="z4-store-btn z4-store-btn-primary" type="submit">{{ $isEdit ? 'Save Template' : 'Create Template' }}</button>@endif</div>
                    </footer>
                </section>
            </form>
        </div>
    </div>

    <script type="application/json" data-template-preview-products>@json($previewProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
    @if($isEdit)<form id="copy-store-template" method="post" action="{{ route('admin.plugins.store.templates.duplicate', $template, false) }}" hidden>@csrf</form>@endif
    @if($isEdit && !$isSystem)<form data-delete-template-form method="post" action="{{ route('admin.plugins.store.templates.destroy', $template, false) }}" hidden>@csrf @method('DELETE')</form>@endif
</main>
</x-app-layout>
