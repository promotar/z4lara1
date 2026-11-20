<x-app-layout>
<main class="z4-store-admin z4-store-category-edit-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <div><a class="z4-store-back" href="{{ route('admin.plugins.store.categories.index') }}">{{ __('store::ui.auto_c9cd8fef7b') }}</a></div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_9b75fc9e2d') }}</span>
                <h1>{{ __('store::ui.auto_176bf027c7') }}</h1>
                <p>{{ __('store::ui.auto_d41e8ceb9a') }}</p>
            </div>
            <div class="z4-store-heading-actions">
                <span class="z4-store-status is-{{ $record->status === 'active' ? 'published' : ($record->status === 'archived' ? 'archived' : 'hidden') }}">{{ $record->status === 'archived' ? 'Deleted' : ucfirst($record->status) }}</span>
            </div>
        </header>

        <div class="z4-store-editor-layout">
            <section class="z4-store-form-card">
                <header class="z4-store-section-heading">
                    <span>1</span>
                    <div>
                        <h2>{{ __('store::ui.auto_d14bceb56a') }}</h2>
                        <p>{{ __('store::ui.auto_31766ed7ba') }}</p>
                    </div>
                </header>

                <form method="post" action="{{ route('admin.plugins.store.categories.update', $record->public_id) }}" class="z4-store-form-grid" data-dirty-form data-z4-store-slug-form>
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="version" value="{{ $record->version }}">
                    <input type="hidden" name="locale" value="{{ app()->getLocale() }}">

                    <label class="is-wide">
                        <span>{{ __('store::ui.auto_49ee308734') }} <b>*</b></span>
                        <input class="z4-store-input" name="name" value="{{ old('name', $record->name) }}" required data-slug-source>
                    </label>
                    <label>
                        <span>{{ __('store::ui.auto_0c90858852') }} <b>*</b></span>
                        <input class="z4-store-input" name="slug" value="{{ old('slug', $record->slug) }}" required pattern="[a-z0-9][a-z0-9-]*" data-slug-target>
                        <small>{{ __('store::ui.auto_1f609e6f26') }}</small>
                    </label>
                    <label>
                        <span>{{ __('store::ui.auto_4e98e60e38') }}</span>
                        <select class="z4-store-select" name="parent">
                            <option value="">{{ __('store::ui.auto_3849abb4cb') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->public_id }}" @selected(old('parent', $record->parent_public_id) === $category->public_id)>{{ str_repeat('— ', $category->depth) }}{{ $category->name ?? $category->public_id }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>{{ __('store::ui.auto_ec53a8c4f0') }}</span>
                        <select class="z4-store-select" name="status">
                            @foreach(['active', 'inactive', 'archived'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $record->status) === $status)>{{ $status === 'archived' ? 'Deleted' : ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>{{ __('store::ui.auto_05acdf5f12') }}</span>
                        <select class="z4-store-select" name="catalog_section">
                            <option value="standard" @selected(old('catalog_section', $record->catalog_section ?: 'standard') === 'standard')>{{ __('store::ui.auto_fafdcf7e63') }}</option>
                            <option value="clothing" @selected(old('catalog_section', $record->catalog_section) === 'clothing')>Clothing &amp; textiles</option>
                        </select>
                    </label>
                    <label>
                        <span>{{ __('store::ui.auto_662d335c75') }}</span>
                        <input type="hidden" name="position" value="{{ $record->position }}">
                        <input class="z4-store-input" type="number" min="1" name="display_position" value="{{ old('display_position', $record->display_position) }}" required>
                        <small>{{ __('store::ui.auto_1c2b693739') }}</small>
                    </label>
                    <label class="z4-store-check is-wide">
                        <input type="checkbox" name="featured" value="1" @checked(old('featured', $record->is_featured))>
                        <span><strong>{{ __('store::ui.auto_85e6d73b00') }}</strong><small>{{ __('store::ui.auto_eceadeb718') }}</small></span>
                    </label>
                    <label class="is-wide">
                        <span>{{ __('store::ui.auto_b5a7adde1a') }}</span>
                        <textarea class="z4-store-textarea" name="description" rows="7" maxlength="5000">{{ old('description', $record->description) }}</textarea>
                    </label>
                    <div class="z4-store-form-actions is-wide">
                        <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.categories.index') }}">{{ __('store::ui.auto_ea4788705e') }}</a>
                        <button class="z4-store-btn z4-store-btn-primary" data-submit-button>{{ __('store::ui.auto_f5d6040ed7') }}</button>
                    </div>
                </form>
            </section>

            <aside class="z4-store-editor-side">
                <section class="z4-store-side-card">
                    <header class="z4-store-section-heading">
                        <span>2</span>
                        <div>
                            <h2>{{ __('store::ui.auto_92d7981ef3') }}</h2>
                            <p>{{ __('store::ui.auto_98bbea97be') }}</p>
                        </div>
                    </header>

                    <div class="z4-store-category-image-preview" data-category-image-preview>
                        @if($record->image_path)
                            <img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($record->image_path) }}" alt="{{ $record->name }}">
                        @else
                            <span>{{ strtoupper(\Illuminate\Support\Str::substr($record->name ?: 'C', 0, 1)) }}</span>
                        @endif
                    </div>

                    <form method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.store.categories.image', $record->public_id) }}" class="z4-store-upload-form">
                        @csrf
                        <label class="z4-store-upload-box">
                            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-category-image-input>
                            <span>＋</span>
                            <strong data-category-image-label>{{ __('store::ui.auto_ceb19075b4') }}</strong>
                            <small>{{ __('store::ui.auto_4cc5e5338d') }}</small>
                        </label>
                        @if($record->image_path)
                            <label class="z4-store-check">
                                <input type="checkbox" name="remove_image" value="1">
                                <span><strong>{{ __('store::ui.auto_b723c70c96') }}</strong><small>{{ __('store::ui.auto_114edad4f9') }}</small></span>
                            </label>
                        @endif
                        <button class="z4-store-btn z4-store-btn-primary z4-store-btn-block" data-submit-button>{{ __('store::ui.auto_9e3c5f9771') }}</button>
                    </form>
                </section>

                <section class="z4-store-side-card">
                    <h2>{{ __('store::ui.auto_822574b40c') }}</h2>
                    <dl>
                        <div><dt>{{ __('store::ui.auto_068f80c751') }}</dt><dd>{{ $record->products_count }}</dd></div>
                        <div><dt>{{ __('store::ui.auto_3b13a7fed3') }}</dt><dd>{{ $record->depth + 1 }}</dd></div>
                        <div><dt>{{ __('store::ui.auto_34b6cd7517') }}</dt><dd>{{ $record->version }}</dd></div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>
</main>
<script>
const categoryImageInput = document.querySelector('[data-category-image-input]');
if (categoryImageInput) {
    categoryImageInput.addEventListener('change', () => {
        const file = categoryImageInput.files[0];
        if (!file) return;

        document.querySelector('[data-category-image-label]').textContent = file.name;
        const preview = document.querySelector('[data-category-image-preview]');
        preview.replaceChildren(Object.assign(document.createElement('img'), {
            src: URL.createObjectURL(file),
            alt: 'Selected category image preview'
        }));
    });
}
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    form.querySelectorAll('[data-submit-button], button[type="submit"], button:not([type])').forEach(button => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    });
}));
</script>
</x-app-layout>
