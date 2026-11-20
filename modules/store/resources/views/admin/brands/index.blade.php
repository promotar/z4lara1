<x-app-layout>
<main class="z4-store-admin z4-store-brands-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')
        <header class="z4-store-heading"><div><span class="z4-store-eyebrow">{{ __('store::ui.auto_9b75fc9e2d') }}</span><h1>{{ __('store::ui.auto_84be54bb4b') }}</h1><p>{{ __('store::ui.auto_4fc443b431') }}</p></div></header>
        <div class="z4-store-management-grid">
            <aside class="z4-store-form-card z4-store-create-panel">
                <header class="z4-store-section-heading"><span>＋</span><div><h2>{{ __('store::ui.auto_ef52356e35') }}</h2><p>{{ __('store::ui.auto_69c36fc2a4') }}</p></div></header>
                <form method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.store.brands.store') }}" data-dirty-form data-brand-create-form data-z4-store-slug-form>@csrf
                    <label><span>{{ __('store::ui.auto_49ee308734') }} <b>*</b></span><input class="z4-store-input" name="name" value="{{ old('name') }}" required data-slug-source></label>
                    <label><span>{{ __('store::ui.auto_0c90858852') }} <b>*</b></span><input class="z4-store-input" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9][a-z0-9-]*" data-slug-target></label>
                    <label class="is-wide"><span>{{ __('store::ui.auto_36e3402e4d') }}</span><textarea class="z4-store-textarea" name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea></label>
                    <label class="is-wide"><span>{{ __('store::ui.auto_db5532cd4a') }}</span><input class="z4-store-input" type="file" name="logo" accept="image/jpeg,image/png,image/webp"><small>{{ __('store::ui.auto_4cc5e5338d') }}</small></label>
                    <label><span>{{ __('store::ui.auto_ec53a8c4f0') }}</span><select class="z4-store-select" name="status"><option value="active" @selected(old('status', 'active') === 'active')>{{ __('store::ui.auto_4d3d769b81') }}</option><option value="hidden" @selected(old('status') === 'hidden')>{{ __('store::ui.auto_7acdf85c69') }}</option></select><small>{{ __('store::ui.auto_84757b7a07') }}</small></label>
                    <label><span>{{ __('store::ui.auto_52f5e0bc38') }}</span><input class="z4-store-input" type="number" name="position" min="0" value="0"></label>
                    <button class="z4-store-btn z4-store-btn-primary z4-store-btn-block" data-submit-button>{{ __('store::ui.auto_ef52356e35') }}</button>
                </form>
            </aside>
            <section class="z4-store-table-card">
                <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_dde2a7a1bb') }}</h2><p>{{ __('store::ui.auto_95bdf3ed59') }}</p></div></header>
                <div class="z4-store-category-list">
                    @forelse($brands as $brand)
                    <article class="z4-store-category-row">
                        <div class="z4-store-category-identity">
                            @if($brand->logo_path)<img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($brand->logo_path) }}" alt="{{ $brand->name }}">@else<span>{{ strtoupper(\Illuminate\Support\Str::substr($brand->name ?: 'B', 0, 2)) }}</span>@endif
                            <div><strong>{{ $brand->name }}</strong><small>{{ __('store::ui.auto_f3a72809b3') }}</small></div>
                        </div>
                        <span class="z4-store-status is-{{ $brand->status === 'active' ? 'published' : ($brand->status === 'archived' ? 'archived' : 'hidden') }}">{{ $brand->status === 'archived' ? 'Deleted' : ucfirst($brand->status) }}</span>
                        @if($brand->status !== 'archived')
                        <details class="z4-store-actions-menu">
                            <summary aria-label="{{ __('store::ui.auto_8ffa5b66c8') }}">•••</summary>
                            <div>
                                <a href="{{ route('admin.plugins.store.brands.index.edit', $brand->public_id) }}">{{ __('store::ui.auto_92d1d735a2') }}</a>
                                <form method="post" action="{{ route('admin.plugins.store.brands.archive', $brand->public_id) }}" data-z4-confirm data-confirm-title="{{ __('store::ui.auto_fc3071565c') }}" data-confirm-message="Products will remain safe and become unbranded. You can assign another brand later." data-confirm-label="Delete brand">
                                    @csrf
                                    <input type="hidden" name="version" value="{{ $brand->version }}">
                                    <button class="is-danger" type="submit">{{ __('store::ui.auto_f2a6c498fb') }}</button>
                                </form>
                            </div>
                        </details>
                        @else
                        <span aria-hidden="true"></span>
                        @endif
                    </article>
                    @empty<div class="z4-store-empty"><span>◇</span><strong>{{ __('store::ui.auto_34ddc7726c') }}</strong><p>{{ __('store::ui.auto_67ff1e2367') }}</p></div>@endforelse
                </div>
                <x-platform-pagination :paginator="$brands" />
            </section>
        </div>
    </div>
</main>
<script>
window.addEventListener('pageshow', () => {
    document.querySelectorAll('.z4-store-actions-menu[open]').forEach(menu => menu.removeAttribute('open'));
});
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    form.querySelectorAll('[data-submit-button], button[type="submit"], button:not([type])').forEach(button => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    });
}));
</script>
</x-app-layout>
