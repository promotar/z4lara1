<x-app-layout>
<main class="z4-store-admin z4-store-categories-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')
        <header class="z4-store-heading"><div><span class="z4-store-eyebrow">{{ __('store::ui.auto_9b75fc9e2d') }}</span><h1>{{ __('store::ui.auto_ef7da766d7') }}</h1><p>{{ __('store::ui.auto_ec125d18bf') }}</p></div></header>

        <div class="z4-store-management-grid">
            <aside class="z4-store-form-card z4-store-create-panel">
                <header class="z4-store-section-heading"><span>＋</span><div><h2>{{ __('store::ui.auto_6e05d18993') }}</h2><p>{{ __('store::ui.auto_1354cb1e7c') }}</p></div></header>
                <form method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.store.categories.store') }}" data-dirty-form data-category-create-form data-z4-store-slug-form>@csrf<input type="hidden" name="locale" value="{{ app()->getLocale() }}">
                    <label><span>{{ __('store::ui.auto_49ee308734') }} <b>*</b></span><input class="z4-store-input" name="name" value="{{ old('name') }}" required data-slug-source></label>
                    <label><span>{{ __('store::ui.auto_0c90858852') }} <b>*</b></span><input class="z4-store-input" name="slug" value="{{ old('slug') }}" required pattern="[a-z0-9][a-z0-9-]*" data-slug-target></label>
                    <label class="is-wide"><span>{{ __('store::ui.auto_36e3402e4d') }}</span><textarea class="z4-store-textarea" name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea></label>
                    <label class="is-wide"><span>{{ __('store::ui.auto_a525762033') }}</span><input class="z4-store-input" type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>{{ __('store::ui.auto_4cc5e5338d') }}</small></label>
                    <label><span>{{ __('store::ui.auto_4e98e60e38') }}</span><select class="z4-store-select" name="parent"><option value="">{{ __('store::ui.auto_3849abb4cb') }}</option>@foreach($categories->where('status', '!=', 'archived') as $category)<option value="{{ $category->public_id }}">{{ str_repeat('— ', $category->depth) }}{{ $category->name ?? $category->public_id }}</option>@endforeach</select></label>
                    <label><span>{{ __('store::ui.auto_05acdf5f12') }}</span><select class="z4-store-select" name="catalog_section"><option value="standard">{{ __('store::ui.auto_fafdcf7e63') }}</option><option value="clothing">Clothing &amp; textiles</option></select></label>
                    <label><span>{{ __('store::ui.auto_52f5e0bc38') }}</span><input class="z4-store-input" type="number" min="0" name="position" value="0"></label>
                    <button class="z4-store-btn z4-store-btn-primary z4-store-btn-block" data-submit-button>{{ __('store::ui.auto_6e05d18993') }}</button>
                </form>
            </aside>

            <section class="z4-store-table-card">
                <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_a6a2a55bea') }}</h2><p>{{ __('store::ui.auto_fe66eb0d06') }}</p></div></header>
                <div class="z4-store-category-list">
                    @forelse($categories as $category)
                    <article class="z4-store-category-row" style="--category-depth: {{ $category->depth }}">
                        <div class="z4-store-category-identity">
                            @if($category->image_path)<img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($category->image_path) }}" alt="">@else<span>{{ strtoupper(\Illuminate\Support\Str::substr($category->name ?: 'C', 0, 1)) }}</span>@endif
                            <div><strong>{{ $category->name }}</strong><small>{{ __('store::ui.auto_7515c71386') }}</small></div>
                        </div>
                        <span class="z4-store-status is-{{ $category->status === 'active' ? 'published' : ($category->status === 'archived' ? 'archived' : 'hidden') }}">{{ $category->status === 'archived' ? 'Deleted' : ucfirst($category->status) }}</span>
                        <details class="z4-store-actions-menu"><summary aria-label="{{ __('store::ui.auto_3468f9fd06') }}">•••</summary><div>
                            <a href="{{ route('admin.plugins.store.categories.index.edit', $category->public_id) }}">{{ __('store::ui.auto_92d1d735a2') }}</a>
                            @if($category->status !== 'archived')<button type="submit" form="archive-category-{{ $category->public_id }}" class="is-danger">{{ __('store::ui.auto_f2a6c498fb') }}</button>@endif
                        </div></details>
                    </article>
                    @empty<div class="z4-store-empty"><span>◫</span><strong>{{ __('store::ui.auto_3dd50082f4') }}</strong><p>{{ __('store::ui.auto_dc53007aee') }}</p></div>@endforelse
                </div>
                <x-platform-pagination :paginator="$categories" />
            </section>
        </div>
        @foreach($categories as $category)@if($category->status !== 'archived')<form id="archive-category-{{ $category->public_id }}" method="post" action="{{ route('admin.plugins.store.categories.update', $category->public_id) }}" data-z4-confirm data-confirm-title="{{ __('store::ui.auto_bbe4e93559') }}" data-confirm-message="Products will remain safe and become uncategorized. You can edit them and assign another category later." data-confirm-label="Delete category">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $category->version }}"><input type="hidden" name="locale" value="{{ app()->getLocale() }}"><input type="hidden" name="name" value="{{ $category->name }}"><input type="hidden" name="slug" value="{{ $category->slug }}"><input type="hidden" name="parent" value="{{ $category->parent_public_id }}"><input type="hidden" name="catalog_section" value="{{ $category->catalog_section ?: 'standard' }}"><input type="hidden" name="description" value="{{ $category->description }}"><input type="hidden" name="featured" value="{{ $category->is_featured ? 1 : 0 }}"><input type="hidden" name="status" value="archived"><input type="hidden" name="position" value="{{ $category->position }}"></form>@endif @endforeach
    </div>
</main>
<script>
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    form.querySelectorAll('[data-submit-button], button[type="submit"], button:not([type])').forEach(button => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    });
}));
</script>
</x-app-layout>
