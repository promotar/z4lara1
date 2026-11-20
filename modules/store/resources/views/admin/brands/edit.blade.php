<x-app-layout>
<main class="z4-store-admin z4-store-category-edit-page z4-store-brand-edit-page" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <div><a class="z4-store-back" href="{{ route('admin.plugins.store.brands.index') }}">{{ __('store::ui.auto_142b586f1b') }}</a></div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_9b75fc9e2d') }}</span>
                <h1>{{ __('store::ui.auto_46dc795826') }}</h1>
                <p>{{ __('store::ui.auto_d41e8ceb9a') }}</p>
            </div>
            <div class="z4-store-heading-actions">
                <span class="z4-store-status is-{{ $record->status === 'active' ? 'published' : ($record->status === 'archived' ? 'archived' : 'hidden') }}">{{ $record->status === 'archived' ? 'Deleted' : ucfirst($record->status) }}</span>
            </div>
        </header>

        <form method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.store.brands.update', $record->public_id) }}" class="z4-store-editor-layout" data-dirty-form data-z4-store-slug-form>
            @csrf
            @method('PATCH')
            <input type="hidden" name="version" value="{{ $record->version }}">

            <section class="z4-store-form-card">
                <header class="z4-store-section-heading">
                    <span>1</span>
                    <div>
                        <h2>{{ __('store::ui.auto_508c08d763') }}</h2>
                        <p>{{ __('store::ui.auto_dde5f21fbb') }}</p>
                    </div>
                </header>

                <div class="z4-store-form-grid">
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
                        <span>{{ __('store::ui.auto_ec53a8c4f0') }}</span>
                        <select class="z4-store-select" name="status">
                            @foreach(['active', 'hidden'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $record->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>{{ __('store::ui.auto_662d335c75') }}</span>
                        <input type="hidden" name="position" value="{{ $record->position }}">
                        <input class="z4-store-input" type="number" min="1" name="display_position" value="{{ old('display_position', $record->display_position) }}" required>
                        <small>{{ __('store::ui.auto_948045c726') }}</small>
                    </label>
                    <label class="is-wide">
                        <span>{{ __('store::ui.auto_b5a7adde1a') }}</span>
                        <textarea class="z4-store-textarea" name="description" rows="8" maxlength="5000">{{ old('description', $record->description) }}</textarea>
                    </label>
                    <div class="z4-store-form-actions is-wide">
                        <a class="z4-store-btn z4-store-btn-ghost" href="{{ route('admin.plugins.store.brands.index') }}">{{ __('store::ui.auto_ea4788705e') }}</a>
                        <button class="z4-store-btn z4-store-btn-primary" data-submit-button>{{ __('store::ui.auto_f5d6040ed7') }}</button>
                    </div>
                </div>
            </section>

            <aside class="z4-store-editor-side">
                <section class="z4-store-side-card">
                    <header class="z4-store-section-heading">
                        <span>2</span>
                        <div>
                            <h2>{{ __('store::ui.auto_6923eceb8b') }}</h2>
                            <p>{{ __('store::ui.auto_e02ecb2ac8') }}</p>
                        </div>
                    </header>

                    <div class="z4-store-category-image-preview" data-brand-logo-preview>
                        @if($record->logo_path)
                            <img src="{{ app(\Modules\Store\Application\Media\StoreMediaUrl::class)->publicPath($record->logo_path) }}" alt="{{ $record->name }}">
                        @else
                            <span>{{ strtoupper(\Illuminate\Support\Str::substr($record->name ?: 'B', 0, 2)) }}</span>
                        @endif
                    </div>
                    <label class="z4-store-upload-box">
                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" data-brand-logo-input>
                        <span>＋</span>
                        <strong data-brand-logo-label>{{ __('store::ui.auto_a7722b8656') }}</strong>
                        <small>{{ __('store::ui.auto_4cc5e5338d') }}</small>
                    </label>
                    @if($record->logo_path)
                        <label class="z4-store-check z4-store-brand-remove-logo">
                            <input type="checkbox" name="remove_logo" value="1">
                            <span><strong>{{ __('store::ui.auto_8bc38487ac') }}</strong><small>{{ __('store::ui.auto_0709116176') }}</small></span>
                        </label>
                    @endif
                </section>

                <section class="z4-store-side-card">
                    <h2>{{ __('store::ui.auto_b6e4a63849') }}</h2>
                    <dl>
                        <div><dt>{{ __('store::ui.auto_c30f9c20e2') }}</dt><dd>{{ $record->products_count }}</dd></div>
                        <div><dt>{{ __('store::ui.auto_9fd320386d') }}</dt><dd>{{ $record->display_position }}</dd></div>
                        <div><dt>{{ __('store::ui.auto_34b6cd7517') }}</dt><dd>{{ $record->version }}</dd></div>
                    </dl>
                </section>
            </aside>
        </form>
    </div>
</main>
<script>
const brandLogoInput = document.querySelector('[data-brand-logo-input]');
if (brandLogoInput) {
    brandLogoInput.addEventListener('change', () => {
        const file = brandLogoInput.files[0];
        if (!file) return;

        document.querySelector('[data-brand-logo-label]').textContent = file.name;
        const preview = document.querySelector('[data-brand-logo-preview]');
        preview.replaceChildren(Object.assign(document.createElement('img'), {
            src: URL.createObjectURL(file),
            alt: 'Selected brand logo preview'
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
