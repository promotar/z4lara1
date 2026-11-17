<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight" data-i18n="core.media.title">{{ __('core.media.title') }}</h2>
    </x-slot>


    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @error('image')
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
            @enderror

            <form id="media-upload-form" method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="hidden">
                @csrf
                <input id="media-upload-input" type="file" name="image" accept=".png,.jpg,.jpeg,.webp,.ico" required>
            </form>

            <form id="bulk-delete-form" method="POST" action="{{ route('admin.media.destroy') }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="media-toolbar">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="media-button media-button-primary" data-upload-trigger>
                            <span class="text-base leading-none">+</span>
                            <span data-i18n="core.media.add">{{ __('core.media.add') }}</span>
                        </button>
                        <button type="button" class="media-button" data-bulk-toggle data-i18n="core.media.bulk">{{ __('core.media.bulk') }}</button>
                        <div data-bulk-actions hidden class="flex flex-wrap items-center gap-2">
                            <button type="submit" form="bulk-delete-form" class="media-button media-button-danger" data-bulk-delete disabled data-i18n="core.media.delete_selected">{{ __('core.media.delete_selected') }}</button>
                            <button type="button" class="media-button" data-bulk-cancel data-i18n="core.common.cancel">{{ __('core.common.cancel') }}</button>
                            <span class="text-sm text-gray-500" data-selected-count>0 selected</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" class="media-button media-button-primary" data-view-mode="grid" data-i18n="core.media.grid">{{ __('core.media.grid') }}</button>
                        <button type="button" class="media-button" data-view-mode="list" data-i18n="core.media.list">{{ __('core.media.list') }}</button>
                    </div>
                </div>
            </section>

            <div class="media-workspace shadow-sm">
                <section class="media-library media-library-panel is-grid" data-media-library>
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.media.library">{{ __('core.media.library') }}</h3>
                        <span class="text-sm text-gray-500">{{ $mediaLibrary->total() }} media files</span>
                    </div>

                    @if ($mediaLibrary->isEmpty())
                        <p class="text-sm text-gray-600" data-i18n="core.media.empty">{{ __('core.media.empty') }}</p>
                    @else
                        <div class="media-grid">
                            @foreach ($mediaLibrary as $item)
                                @php
                                    $metadata = $item['metadata'] ?? [];
                                @endphp
                                <article
                                    class="media-item"
                                    data-media-item
                                    data-url="{{ $item['url'] }}"
                                    data-name="{{ $item['name'] }}"
                                    data-directory="{{ $item['directory'] }}"
                                    data-size="{{ $item['size'] }}"
                                    data-modified="{{ $item['modified_at'] }}"
                                    data-alt="{{ $metadata['alt_text'] ?? '' }}"
                                    data-title="{{ $metadata['title'] ?? '' }}"
                                    data-caption="{{ $metadata['caption'] ?? '' }}"
                                    data-description="{{ $metadata['description'] ?? '' }}"
                                >
                                    <input form="bulk-delete-form" type="checkbox" name="media_urls[]" value="{{ $item['url'] }}" class="media-select" data-media-checkbox>
                                    <button type="button" class="media-thumb" data-open-details>
                                        <img src="{{ $item['url'] }}" alt="{{ $metadata['alt_text'] ?: $item['name'] }}">
                                    </button>
                                    <div class="media-name">{{ $item['name'] }}</div>
                                    <div class="media-list-meta">{{ $item['size'] }}</div>
                                    <div class="media-list-meta">{{ $item['modified_at'] }}</div>
                                </article>
                            @endforeach
                        </div>
                        <x-platform-pagination :paginator="$mediaLibrary" page-name="media_page" />
                    @endif
                </section>

                <aside class="media-details" data-media-details>
                    <h3 class="media-details-title" data-i18n="core.media.details">{{ __('core.media.details') }}</h3>
                    <p class="media-details-help" data-i18n="core.media.details_hint">{{ __('core.media.details_hint') }}</p>

                    <div data-empty-details class="mt-6 rounded-md border border-dashed border-gray-300 p-5 text-sm text-gray-500" data-i18n="core.media.no_image">
                        {{ __('core.media.no_image') }}
                    </div>

                    <div data-filled-details hidden class="mt-5 space-y-4">
                        <img src="" alt="" data-details-image>
                        <div>
                            <div class="text-sm font-semibold text-gray-900" data-details-name></div>
                            <div class="media-details-meta mt-1" data-details-meta></div>
                        </div>

                        <label class="media-seo-field">
                            <span data-i18n="core.media.file_url">{{ __('core.media.file_url') }}</span>
                            <input readonly data-details-url class="bg-white">
                        </label>

                        <form method="POST" action="{{ route('admin.media.update') }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="media_url" data-details-media-url>

                            <label class="media-seo-field">
                                <span data-i18n="core.media.alt">{{ __('core.media.alt') }}</span>
                                <textarea name="alt_text" rows="3" data-details-alt></textarea>
                            </label>
                            <label class="media-seo-field">
                                <span data-i18n="core.media.title_field">{{ __('core.media.title_field') }}</span>
                                <input name="title" data-details-title>
                            </label>
                            <label class="media-seo-field">
                                <span data-i18n="core.media.caption">{{ __('core.media.caption') }}</span>
                                <textarea name="caption" rows="3" data-details-caption></textarea>
                            </label>
                            <label class="media-seo-field">
                                <span data-i18n="core.media.description">{{ __('core.media.description') }}</span>
                                <textarea name="description" rows="4" data-details-description></textarea>
                            </label>

                            <div class="media-details-actions">
                                <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" data-i18n="core.media.save_seo">{{ __('core.media.save_seo') }}</button>
                            </div>
                        </form>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const uploadInput = document.querySelector('[data-upload-trigger]');
            const fileInput = document.querySelector('#media-upload-input');
            const uploadForm = document.querySelector('#media-upload-form');
            const library = document.querySelector('[data-media-library]');
            const bulkToggle = document.querySelector('[data-bulk-toggle]');
            const bulkCancel = document.querySelector('[data-bulk-cancel]');
            const bulkActions = document.querySelector('[data-bulk-actions]');
            const bulkDelete = document.querySelector('[data-bulk-delete]');
            const selectedCount = document.querySelector('[data-selected-count]');
            const checkboxes = Array.from(document.querySelectorAll('[data-media-checkbox]'));
            const items = Array.from(document.querySelectorAll('[data-media-item]'));

            const emptyDetails = document.querySelector('[data-empty-details]');
            const filledDetails = document.querySelector('[data-filled-details]');
            const detailsImage = document.querySelector('[data-details-image]');
            const detailsName = document.querySelector('[data-details-name]');
            const detailsMeta = document.querySelector('[data-details-meta]');
            const detailsUrl = document.querySelector('[data-details-url]');
            const detailsMediaUrl = document.querySelector('[data-details-media-url]');
            const detailsAlt = document.querySelector('[data-details-alt]');
            const detailsTitle = document.querySelector('[data-details-title]');
            const detailsCaption = document.querySelector('[data-details-caption]');
            const detailsDescription = document.querySelector('[data-details-description]');

            if (bulkActions) {
                bulkActions.hidden = true;
                bulkActions.style.display = 'none';
            }

            if (bulkToggle) {
                bulkToggle.hidden = false;
                bulkToggle.style.display = 'inline-flex';
            }

            const updateBulkState = () => {
                const selected = checkboxes.filter((checkbox) => checkbox.checked);
                selectedCount.textContent = `${selected.length} selected`;
                bulkDelete.disabled = selected.length === 0;

                items.forEach((item) => {
                    const checkbox = item.querySelector('[data-media-checkbox]');
                    item.classList.toggle('is-selected', checkbox?.checked === true);
                });
            };

            uploadInput?.addEventListener('click', () => fileInput?.click());
            fileInput?.addEventListener('change', () => {
                if (fileInput.files.length > 0) {
                    uploadForm.submit();
                }
            });

            document.querySelectorAll('[data-view-mode]').forEach((button) => {
                button.addEventListener('click', () => {
                    const mode = button.dataset.viewMode;
                    library.classList.toggle('is-list', mode === 'list');
                    library.classList.toggle('is-grid', mode === 'grid');

                    document.querySelectorAll('[data-view-mode]').forEach((item) => item.classList.remove('media-button-primary'));
                    button.classList.add('media-button-primary');
                });
            });

            bulkToggle?.addEventListener('click', () => {
                library.classList.add('is-bulk');
                bulkActions.hidden = false;
                bulkActions.style.display = 'flex';
                bulkToggle.hidden = true;
                bulkToggle.style.display = 'none';
                updateBulkState();
            });

            bulkCancel?.addEventListener('click', () => {
                library.classList.remove('is-bulk');
                bulkActions.hidden = true;
                bulkActions.style.display = 'none';
                bulkToggle.hidden = false;
                bulkToggle.style.display = 'inline-flex';
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
                updateBulkState();
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', updateBulkState);
            });

            document.querySelector('#bulk-delete-form')?.addEventListener('submit', (event) => {
                const selected = checkboxes.filter((checkbox) => checkbox.checked);

                if (selected.length === 0 || ! confirm(`Delete ${selected.length} selected media file(s)?`)) {
                    event.preventDefault();
                }
            });

            items.forEach((item) => {
                item.querySelector('[data-open-details]')?.addEventListener('click', () => {
                    if (library.classList.contains('is-bulk')) {
                        const checkbox = item.querySelector('[data-media-checkbox]');

                        if (checkbox) {
                            checkbox.checked = ! checkbox.checked;
                            updateBulkState();
                        }

                        return;
                    }

                    items.forEach((other) => other.classList.remove('is-selected'));
                    item.classList.add('is-selected');

                    emptyDetails.hidden = true;
                    filledDetails.hidden = false;

                    detailsImage.src = item.dataset.url;
                    detailsImage.alt = item.dataset.alt || item.dataset.name;
                    detailsName.textContent = item.dataset.name;
                    detailsMeta.textContent = `${item.dataset.directory || 'storage'} / ${item.dataset.size} / ${item.dataset.modified}`;
                    detailsUrl.value = `${window.location.origin}${item.dataset.url}`;
                    detailsMediaUrl.value = item.dataset.url;
                    detailsAlt.value = item.dataset.alt || '';
                    detailsTitle.value = item.dataset.title || '';
                    detailsCaption.value = item.dataset.caption || '';
                    detailsDescription.value = item.dataset.description || '';
                });
            });
        });
    </script>
</x-app-layout>
