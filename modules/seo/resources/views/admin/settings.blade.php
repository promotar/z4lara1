<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('seo::ui.settings') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.plugins.seo.settings.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PATCH')

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="font-semibold text-gray-900">{{ __('seo::ui.website_information') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.website_information_help') }}</p>
                    </div>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="seo_site_name" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.website_name') }}</label>
                            <input id="seo_site_name" name="site_name" value="{{ old('site_name', $settings['site_name']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label for="seo_default_description" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.default_description') }}</label>
                            <textarea id="seo_default_description" name="default_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('default_description', $settings['default_description']) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.default_description_help') }}</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="seo_default_og_image" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.default_social_image') }}</label>
                                <input id="seo_default_og_image" name="default_og_image" value="{{ old('default_og_image', $settings['default_og_image']) }}" placeholder="/storage/..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                <button type="button" data-seo-media-open data-target="seo_default_og_image" class="mt-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.choose_media') }}</button>
                                <img data-seo-media-preview="seo_default_og_image" data-seo-media-open data-target="seo_default_og_image" src="{{ $settings['default_og_image'] }}" alt="" title="Open Media Studio" role="button" tabindex="0" class="mt-3 h-20 w-auto cursor-pointer rounded border border-gray-200 object-contain" style="max-height:80px;width:auto;" @if (! $settings['default_og_image']) hidden @endif>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="font-semibold text-gray-900">{{ __('seo::ui.titles_indexing') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.titles_indexing_help') }}</p>
                    </div>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="seo_title_template" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.default_title_template') }}</label>
                            <input id="seo_title_template" name="title_template" value="{{ old('title_template', $settings['title_template']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.title_template_help') }}</p>
                        </div>

                        <div>
                            <label for="seo_description_template" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.default_description_template') }}</label>
                            <input id="seo_description_template" name="description_template" value="{{ old('description_template', $settings['description_template']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.description_template_help') }}</p>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="seo_robots_behavior" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.default_robots') }}</label>
                                <select id="seo_robots_behavior" name="robots_behavior" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    @foreach (['index_follow', 'noindex_follow', 'index_nofollow', 'noindex_nofollow'] as $behavior)
                                        <option value="{{ $behavior }}" @selected(old('robots_behavior', $settings['robots_behavior']) === $behavior)>{{ __('seo::ui.robots_'.$behavior) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="seo_canonical_behavior" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.default_canonical') }}</label>
                                <select id="seo_canonical_behavior" name="canonical_behavior" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                    @foreach (['auto', 'request', 'disabled'] as $behavior)
                                        <option value="{{ $behavior }}" @selected(old('canonical_behavior', $settings['canonical_behavior']) === $behavior)>{{ __('seo::ui.canonical_'.$behavior) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="font-semibold text-gray-900">{{ __('seo::ui.social_sharing') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.social_sharing_help') }}</p>
                    </div>

                    <div class="mt-5 space-y-5">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="og_enabled" value="0">
                            <input type="checkbox" name="og_enabled" value="1" @checked(old('og_enabled', $settings['og_enabled']))>
                            {{ __('seo::ui.og_enabled') }}
                        </label>

                        <div>
                            <label for="seo_social_title" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.social_title') }}</label>
                            <input id="seo_social_title" name="social_title" value="{{ old('social_title', $settings['social_title']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.social_title_help') }}</p>
                        </div>

                        <div>
                            <label for="seo_social_description" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.social_description') }}</label>
                            <textarea id="seo_social_description" name="social_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">{{ old('social_description', $settings['social_description']) }}</textarea>
                        </div>

                        <div>
                            <label for="seo_social_image" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.social_image') }}</label>
                            <input id="seo_social_image" name="social_image" value="{{ old('social_image', $settings['social_image']) }}" placeholder="/storage/..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <button type="button" data-seo-media-open data-target="seo_social_image" class="mt-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.choose_media') }}</button>
                            <img data-seo-media-preview="seo_social_image" data-seo-media-open data-target="seo_social_image" src="{{ $settings['social_image'] }}" alt="" title="Open Media Studio" role="button" tabindex="0" class="mt-3 h-20 w-auto cursor-pointer rounded border border-gray-200 object-contain" style="max-height:80px;width:auto;" @if (! $settings['social_image']) hidden @endif>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="border-b border-gray-100 pb-3">
                        <h3 class="font-semibold text-gray-900">{{ __('seo::ui.organization_information') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.organization_information_help') }}</p>
                    </div>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="seo_organization_name" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.organization_name') }}</label>
                            <input id="seo_organization_name" name="organization_name" value="{{ old('organization_name', $settings['organization_name']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div class="sm:col-span-2">
                            <label for="seo_organization_logo" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.organization_logo') }}</label>
                            <input id="seo_organization_logo" name="organization_logo" value="{{ old('organization_logo', $settings['organization_logo']) }}" placeholder="/storage/..." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <button type="button" data-seo-media-open data-target="seo_organization_logo" class="mt-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.choose_media') }}</button>
                            <img data-seo-media-preview="seo_organization_logo" data-seo-media-open data-target="seo_organization_logo" src="{{ $settings['organization_logo'] }}" alt="" title="Open Media Studio" role="button" tabindex="0" class="mt-3 h-16 w-auto cursor-pointer rounded border border-gray-200 object-contain" style="max-height:64px;width:auto;" @if (! $settings['organization_logo']) hidden @endif>
                        </div>

                        <div>
                            <label for="seo_organization_url" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.organization_url') }}</label>
                            <input id="seo_organization_url" type="url" name="organization_url" value="{{ old('organization_url', $settings['organization_url']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label for="seo_organization_email" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.organization_email') }}</label>
                            <input id="seo_organization_email" type="email" name="organization_email" value="{{ old('organization_email', $settings['organization_email']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>

                        <div>
                            <label for="seo_organization_phone" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.organization_phone') }}</label>
                            <input id="seo_organization_phone" name="organization_phone" value="{{ old('organization_phone', $settings['organization_phone']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        </div>
                    </div>
                </section>

                <div>
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('seo::ui.save') }}</button>
                </div>
            </form>
        </div>
    </div>

<style>
    .seo-media-modal { position: fixed; inset: 0; z-index: 90; display: none; }
    .seo-media-modal.is-open { display: block; }
    .seo-media-backdrop { position: absolute; inset: 0; background: rgba(17, 24, 39, .55); }
    .seo-media-panel { position: relative; width: min(920px, calc(100% - 32px)); max-height: 86vh; margin: 56px auto 0; background: #fff; border-radius: 12px; box-shadow: 0 24px 70px rgba(15, 23, 42, .28); overflow: hidden; display: flex; flex-direction: column; }
    .seo-media-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; border-bottom: 1px solid #e5e7eb; padding: 18px 20px; }
    .seo-media-head h3 { margin: 0; color: #111827; font-size: 18px; font-weight: 700; }
    .seo-media-head p { margin: 5px 0 0; color: #6b7280; font-size: 12px; }
    .seo-media-close { border: 0; background: transparent; color: #6b7280; font-size: 24px; line-height: 1; cursor: pointer; padding: 2px 6px; border-radius: 5px; }
    .seo-media-close:hover { background: #f3f4f6; }
    .seo-media-toolbar { display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f3f4f6; padding: 12px 20px; flex-wrap: wrap; }
    .seo-media-upload-btn { border: 0; border-radius: 6px; background: #111827; color: #fff; padding: 8px 12px; font-size: 12px; font-weight: 700; cursor: pointer; }
    .seo-media-upload-btn:hover { background: #1f2937; }
    .seo-media-status { color: #6b7280; font-size: 11px; }
    .seo-media-body { padding: 20px; overflow-y: auto; }
    .seo-media-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    @media (min-width: 640px) { .seo-media-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    @media (min-width: 900px) { .seo-media-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
    .seo-media-item { display: block; width: 100%; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; padding: 0; overflow: hidden; cursor: pointer; text-align: left; }
    .seo-media-item:hover { border-color: #9a0000; box-shadow: 0 0 0 2px rgba(154, 0, 0, .12); }
    .seo-media-thumb { display: block; width: 100%; height: 108px; object-fit: cover; background: #f3f4f6; }
    .seo-media-name { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 7px 9px; color: #4b5563; font-size: 11px; }
    .seo-media-empty { margin: 16px 0 0; color: #6b7280; font-size: 13px; }
</style>
<div data-seo-media-modal class="seo-media-modal">
    <div class="seo-media-backdrop" data-seo-media-close></div>
    <div class="seo-media-panel">
        <div class="seo-media-head">
            <div>
                <h3>Media Studio</h3>
                <p>Select an image from the platform media library or upload a new one.</p>
            </div>
            <button type="button" class="seo-media-close" data-seo-media-close aria-label="Close">&times;</button>
        </div>
        <div class="seo-media-toolbar">
            <button type="button" class="seo-media-upload-btn" data-seo-media-upload-trigger>Upload image</button>
            <span class="seo-media-status" data-seo-media-status></span>
        </div>
        <div class="seo-media-body">
            <div class="seo-media-grid" data-seo-media-grid></div>
            <p class="seo-media-empty" data-seo-media-empty hidden>No images are available.</p>
        </div>
    </div>
</div>
<form data-seo-media-upload style="display:none">@csrf<input type="file" name="image" accept="image/*" data-seo-media-file></form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.querySelector('[data-seo-media-modal]');
    const grid = document.querySelector('[data-seo-media-grid]');
    const empty = document.querySelector('[data-seo-media-empty]');
    const status = document.querySelector('[data-seo-media-status]');
    const file = document.querySelector('[data-seo-media-file]');
    const uploadForm = document.querySelector('[data-seo-media-upload]');
    const mediaIndexUrl = @json(route('admin.plugins.seo.media.index'));
    const mediaStoreUrl = @json(route('admin.plugins.seo.media.store'));
    let target = null;

    const previewFor = (input) => document.querySelector('[data-seo-media-preview="' + input.id + '"]');

    const updatePreview = (input) => {
        const preview = previewFor(input);
        if (!preview) return;
        const value = String(input.value || '').trim();
        preview.hidden = value === '';
        if (value !== '') preview.src = value;
    };

    const close = () => { modal.classList.remove('is-open'); target = null; };

    const choose = (url) => {
        if (!target) return;
        target.value = url;
        target.dispatchEvent(new Event('input', { bubbles: true }));
        updatePreview(target);
        close();
    };

    const render = (items) => {
        grid.innerHTML = '';
        const images = (items || []).filter((item) => item.image_url || item.thumbnail_url || /^image\//.test(String(item.mime_type || '')));
        empty.hidden = images.length > 0;
        images.forEach((item) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'seo-media-item';
            const img = document.createElement('img');
            img.src = item.thumbnail_url || item.image_url || item.url;
            img.alt = item.name || '';
            img.className = 'seo-media-thumb';
            button.appendChild(img);
            const label = document.createElement('span');
            label.className = 'seo-media-name';
            label.textContent = item.name || 'Image';
            button.appendChild(label);
            button.addEventListener('click', () => choose(item.url));
            grid.appendChild(button);
        });
    };

    const load = async () => {
        status.textContent = 'Loading media...';
        try {
            const response = await fetch(mediaIndexUrl + '?type=image', { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Media library could not be loaded.');
            const data = await response.json();
            render(data.items || []);
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message || 'Media library could not be loaded.';
        }
    };

    document.querySelectorAll('[data-seo-media-open]').forEach((button) => {
        button.addEventListener('click', () => {
            target = document.getElementById(button.dataset.target || '');
            if (!target) return;
            modal.classList.add('is-open');
            load();
        });
        button.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                button.click();
            }
        });
    });

    document.querySelectorAll('[data-seo-media-close]').forEach((button) => button.addEventListener('click', close));
    document.querySelector('[data-seo-media-upload-trigger]')?.addEventListener('click', () => file.click());

    file?.addEventListener('change', async () => {
        if (!file.files.length) return;
        status.textContent = 'Uploading...';
        const body = new FormData();
        body.append('image', file.files[0]);
        try {
            const response = await fetch(mediaStoreUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': uploadForm.querySelector('input[name="_token"]').value,
                },
                body,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(data.message || 'Upload failed.');
            choose(data.media.url);
        } catch (error) {
            status.textContent = error.message || 'Upload failed.';
        } finally {
            file.value = '';
        }
    });

    document.querySelectorAll('input[name="default_og_image"], input[name="social_image"], input[name="organization_logo"]').forEach((input) => {
        input.addEventListener('input', () => updatePreview(input));
    });
});
</script>

</x-app-layout>
