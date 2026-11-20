<x-app-layout>
<main class="z4-store-admin z4-store-attributes-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_55d3579c25') }}</span>
                <h1>{{ __('store::ui.auto_b54ebc45d5') }}</h1>
                <p>{{ __('store::ui.auto_20188c6965') }}</p>
            </div>
        </header>

        <div class="z4-store-management-grid">
            <aside class="z4-store-form-card z4-store-create-panel">
                <header class="z4-store-section-heading"><span>＋</span><div><h2>{{ __('store::ui.auto_7babdf054c') }}</h2><p>{{ __('store::ui.auto_698a49de64') }}</p></div></header>
                <form method="post" action="{{ route('admin.plugins.store.attributes.store') }}" data-dirty-form>
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
                    <label><span>{{ __('store::ui.auto_49ee308734') }} <b>*</b></span><input class="z4-store-input" name="name" required placeholder="{{ __('store::ui.auto_d679f3ea89') }}"></label>
                    <label><span>{{ __('store::ui.auto_ca0dbad92a') }} <b>*</b></span><input class="z4-store-input" name="code" required placeholder="{{ __('store::ui.auto_eec34d804c') }}"></label>
                    <label><span>{{ __('store::ui.auto_3c8a58a423') }}</span><select class="z4-store-select" name="data_type">@foreach(['text','integer','decimal','boolean','select'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach</select></label>
                    <div class="z4-store-check-stack">
                        <label class="z4-store-check"><input type="checkbox" name="visible" value="1" checked><span><strong>{{ __('store::ui.auto_ec24d78ce3') }}</strong><small>{{ __('store::ui.auto_4c190ffc80') }}</small></span></label>
                        <label class="z4-store-check"><input type="checkbox" name="filterable" value="1"><span><strong>{{ __('store::ui.auto_e7373925b4') }}</strong><small>{{ __('store::ui.auto_d0f9c01b15') }}</small></span></label>
                        <label class="z4-store-check"><input type="checkbox" name="supports_variant_axis" value="1"><span><strong>{{ __('store::ui.auto_9b468130c9') }}</strong><small>{{ __('store::ui.auto_84cae0e7d6') }}</small></span></label>
                    </div>
                    <button class="z4-store-btn z4-store-btn-primary z4-store-btn-block" data-submit-button>{{ __('store::ui.auto_5f93f38de7') }}</button>
                </form>
            </aside>

            <section class="z4-store-table-card">
                <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_a4d027bb0e') }}</h2><p>{{ __('store::ui.auto_89e7837e17') }}</p></div></header>
                <div class="z4-store-record-list">
                    @forelse($attributes as $attribute)
                    <article class="z4-store-record-card">
                        <div class="z4-store-record-icon">A</div>
                        <div class="z4-store-record-copy">
                            <strong>{{ $attribute->name ?? $attribute->code }}</strong>
                            <span>{{ __('store::ui.auto_e959bd6a1f') }}</span>
                            <div class="z4-store-badges">
                                @if($attribute->is_visible)<small>{{ __('store::ui.auto_ec24d78ce3') }}</small>@endif
                                @if($attribute->is_filterable)<small>{{ __('store::ui.auto_e7373925b4') }}</small>@endif
                                @if($attribute->supports_variant_axis)<small>{{ __('store::ui.auto_9b468130c9') }}</small>@endif
                            </div>
                        </div>
                        <span class="z4-store-status is-{{ $attribute->status === 'active' ? 'published' : ($attribute->status === 'archived' ? 'archived' : 'hidden') }}">{{ $attribute->status === 'archived' ? 'Deleted' : ucfirst($attribute->status) }}</span>
                        <details class="z4-store-actions-menu"><summary aria-label="{{ __('store::ui.auto_6a0a229682') }}">•••</summary><div><button type="button" data-toggle-panel="attribute-{{ $attribute->public_id }}">{{ __('store::ui.auto_9bd3a068ee') }}</button></div></details>

                        <div class="z4-store-inline-editor" id="attribute-{{ $attribute->public_id }}" hidden>
                            <form method="post" action="{{ route('admin.plugins.store.attributes.status', $attribute->public_id) }}" class="z4-store-inline-action">
                                @csrf @method('PATCH')
                                <label><span>{{ __('store::ui.auto_ec53a8c4f0') }}</span><select class="z4-store-select" name="status">@foreach(['active','inactive','archived'] as $status)<option value="{{ $status }}" @selected($attribute->status === $status)>{{ $status === 'archived' ? 'Deleted' : ucfirst($status) }}</option>@endforeach</select></label>
                                <button class="z4-store-btn z4-store-btn-primary" data-submit-button>{{ __('store::ui.auto_a90e348625') }}</button>
                            </form>

                            @if($attribute->data_type === 'select')
                            <form method="post" action="{{ route('admin.plugins.store.attributes.options.store', $attribute->public_id) }}" class="z4-store-option-form">
                                @csrf
                                <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
                                <label><span>{{ __('store::ui.auto_cad8a76c4d') }}</span><input class="z4-store-input" name="name" required placeholder="{{ __('store::ui.auto_31de06b9c2') }}"></label>
                                <label><span>{{ __('store::ui.auto_c91d998a0a') }}</span><input class="z4-store-input" name="code" required placeholder="{{ __('store::ui.auto_e9cefb4693') }}"></label>
                                <label><span>{{ __('store::ui.auto_52f5e0bc38') }}</span><input type="number" min="0" class="z4-store-input" name="position" value="0"></label>
                                <button class="z4-store-btn z4-store-btn-ghost" data-submit-button>{{ __('store::ui.auto_704468d3ab') }}</button>
                            </form>
                            @endif

                            @if($attribute->options->isNotEmpty())
                            <div class="z4-store-token-list">
                                @foreach($attribute->options as $option)
                                <form method="post" action="{{ route('admin.plugins.store.attributes.options.status', [$attribute->public_id, $option->public_id]) }}">
                                    @csrf @method('PATCH')
                                    <span>{{ $option->name ?? $option->code }}</span>
                                    <select class="z4-store-select" name="status" onchange="this.form.submit()">@foreach(['active','inactive','archived'] as $status)<option value="{{ $status }}" @selected($option->status === $status)>{{ $status === 'archived' ? 'Deleted' : ucfirst($status) }}</option>@endforeach</select>
                                </form>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </article>
                    @empty
                    <div class="z4-store-empty"><span>◇</span><strong>{{ __('store::ui.auto_9469e0aab5') }}</strong><p>{{ __('store::ui.auto_48e8960677') }}</p></div>
                    @endforelse
                </div>
                <x-platform-pagination :paginator="$attributes" />
            </section>
        </div>
    </div>
</main>
<script>
document.querySelectorAll('[data-toggle-panel]').forEach(button => button.addEventListener('click', () => {
    const panel = document.getElementById(button.dataset.togglePanel);
    panel.hidden = !panel.hidden;
}));
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    form.querySelectorAll('[data-submit-button]').forEach(button => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    });
}));
</script>
</x-app-layout>
