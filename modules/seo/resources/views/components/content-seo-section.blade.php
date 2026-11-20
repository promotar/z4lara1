@php
    $custom = $custom ?? [];
    $preview = $preview ?? [];
    $contentId = $contentId ?? null;
    $contentType = $contentType ?? 'content';
    $seoLocale = $locale ?? '';
    $titleSelector = $titleSelector ?? 'input[name="title"]';
    $descriptionSelector = $descriptionSelector ?? 'textarea[name="excerpt"]';
    $slugSelector = $slugSelector ?? 'input[name="slug"]';
    $imageSelector = $imageSelector ?? '';
    $imageWatchSelector = $imageWatchSelector ?? '';
@endphp

<section class="content-seo-panel"
    data-content-seo
    data-seo-preview='@json($preview)'
    data-seo-selectors='{{ json_encode([
        "title" => $titleSelector,
        "description" => $descriptionSelector,
        "slug" => $slugSelector,
        "image" => $imageSelector,
        "imageWatch" => $imageWatchSelector,
    ], JSON_UNESCAPED_SLASHES) }}'>
    <style>
        .content-seo-panel { border: 1px solid #e6e1df; border-radius: 10px; background: #fff; margin: 18px 0; overflow: hidden; }
        .content-seo-panel-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 18px 20px 14px; border-bottom: 1px solid #f0e9e7; }
        .content-seo-panel-head h2 { margin: 0; color: #2b2020; font-size: 17px; font-weight: 800; }
        .content-seo-locale { display: inline-block; margin-left: 7px; padding: 2px 7px; border-radius: 20px; background: #f4efed; color: #7c1d1d; font-size: 10px; font-weight: 800; vertical-align: middle; }
        .content-seo-panel-head p { margin: 5px 0 0; color: #716767; font-size: 12px; line-height: 1.5; }
        .content-seo-body { display: grid; grid-template-columns: minmax(0, 1fr) minmax(260px, .9fr); gap: 24px; padding: 20px; }
        .content-seo-fields { display: grid; gap: 14px; align-content: start; }
        .content-seo-field { display: grid; gap: 6px; }
        .content-seo-field > span { color: #443a3a; font-size: 12px; font-weight: 700; }
        .content-seo-field small { color: #8a7f7f; font-size: 11px; line-height: 1.45; }
        .content-seo-panel input[type="text"], .content-seo-panel input[type="url"], .content-seo-panel textarea, .content-seo-panel select {
            width: 100% !important; box-sizing: border-box; border: 1px solid #dcd3d0 !important; border-radius: 7px !important;
            background: #fff !important; color: #2f2727 !important; padding: 9px 11px !important; font-size: 13px !important;
            line-height: 1.4 !important; box-shadow: none !important;
        }
        .content-seo-panel textarea { min-height: 78px; resize: vertical; }
        .content-seo-panel input:focus, .content-seo-panel textarea:focus, .content-seo-panel select:focus { border-color: #9a0000 !important; outline: 0; box-shadow: 0 0 0 3px rgba(154, 0, 0, .09) !important; }
        .content-seo-split { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .content-seo-preview { display: grid; gap: 10px; align-content: start; }
        .content-seo-preview-card { border: 1px solid #e6e1df; border-radius: 9px; background: #fff; padding: 13px 14px; min-height: 118px; overflow: hidden; }
        .content-seo-preview-label { display: block; margin-bottom: 8px; color: #8a7f7f; font-size: 10px; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .content-seo-google-title { color: #1a0dab; font-size: 17px; line-height: 1.35; overflow-wrap: anywhere; }
        .content-seo-google-url { color: #006621; font-size: 11px; margin-top: 3px; overflow-wrap: anywhere; }
        .content-seo-google-description { color: #4d5156; font-size: 12px; line-height: 1.5; margin-top: 5px; overflow-wrap: anywhere; }
        .content-seo-social { padding: 0; }
        .content-seo-social-image { display: block; width: 100%; height: 118px; object-fit: cover; background: #f4efed; border-bottom: 1px solid #eee7e5; }
        .content-seo-social-placeholder { display: flex; width: 100%; height: 118px; align-items: center; justify-content: center; border-bottom: 1px solid #eee7e5; background: #f7f1ef; color: #9a8d8a; font-size: 11px; }
        .content-seo-social-copy { padding: 11px 13px 13px; }
        .content-seo-social-url { color: #8a7f7f; font-size: 10px; text-transform: uppercase; overflow-wrap: anywhere; }
        .content-seo-social-title { margin-top: 4px; color: #2b2020; font-size: 14px; font-weight: 800; line-height: 1.35; overflow-wrap: anywhere; }
        .content-seo-social-description { margin-top: 3px; color: #716767; font-size: 11px; line-height: 1.45; overflow-wrap: anywhere; }
        .content-seo-note { padding: 0 20px 16px; color: #9a8d8a; font-size: 10.5px; }
        @media (max-width: 1050px) { .content-seo-body { grid-template-columns: 1fr; } }
        @media (max-width: 640px) { .content-seo-split { grid-template-columns: 1fr; } .content-seo-body { padding: 16px; } }
    </style>

    <div class="content-seo-panel-head">
        <div>
            <h2>SEO @if($seoLocale !== '')<span class="content-seo-locale">{{ strtoupper($seoLocale) }}</span> @endif</h2>
            <p>Custom values for this language override the defaults generated by global SEO Settings. Leave a field empty to use the generated value.</p>
        </div>
    </div>

    <div class="content-seo-body">
        <div class="content-seo-fields">
            <label class="content-seo-field">
                <span>SEO Title</span>
                <input type="text" name="seo_title" data-seo-title maxlength="255" value="{{ old('seo_title', $custom['title'] ?? '') }}" placeholder="{{ $preview['effectiveTitle'] ?? '' }}">
                <small>Defaults to the content title plus the website name.</small>
            </label>

            <label class="content-seo-field">
                <span>Meta Description</span>
                <textarea name="seo_description" data-seo-description maxlength="500" placeholder="{{ $preview['effectiveDescription'] ?? '' }}">{{ old('seo_description', $custom['description'] ?? '') }}</textarea>
                <small>Defaults to the content excerpt, description or the global description.</small>
            </label>

            <label class="content-seo-field">
                <span>Focus Keyword</span>
                <input type="text" name="focus_keyword" maxlength="255" value="{{ old('focus_keyword', $custom['focus_keyword'] ?? '') }}" placeholder="Optional editorial keyword">
                <small>Optional; used for editorial focus only.</small>
            </label>

            <div class="content-seo-split">
                <label class="content-seo-field">
                    <span>Robots</span>
                    <select name="seo_robots">
                        <option value="" @selected(old('seo_robots', $custom['robots'] ?? '') === '')>Use global default</option>
                        <option value="index" @selected(old('seo_robots', $custom['robots'] ?? '') === 'index')>Index</option>
                        <option value="noindex" @selected(old('seo_robots', $custom['robots'] ?? '') === 'noindex')>Noindex</option>
                    </select>
                </label>

                <label class="content-seo-field">
                    <span>Canonical URL</span>
                    <input type="url" name="canonical_url" maxlength="2048" value="{{ old('canonical_url', $custom['canonical'] ?? '') }}" placeholder="{{ $preview['effectiveCanonical'] ?? '' }}">
                    <small>Leave empty to use the content URL.</small>
                </label>
            </div>
        </div>

        <div class="content-seo-preview">
            <div class="content-seo-preview-card">
                <span class="content-seo-preview-label">Google Search Preview</span>
                <div class="content-seo-google-title" data-seo-google-title>{{ $preview['effectiveTitle'] ?? '' }}</div>
                <div class="content-seo-google-url" data-seo-google-url>{{ $preview['effectiveCanonical'] ?? '' }}</div>
                <div class="content-seo-google-description" data-seo-google-description>{{ $preview['effectiveDescription'] ?? '' }}</div>
            </div>

            <div class="content-seo-preview-card content-seo-social">
                <span class="content-seo-preview-label">Social Sharing Preview</span>
                <img class="content-seo-social-image" data-seo-social-image src="{{ $preview['effectiveImage'] ?? '' }}" alt="" @if(empty($preview['effectiveImage'])) hidden @endif>
                <div class="content-seo-social-placeholder" data-seo-social-placeholder @if(!empty($preview['effectiveImage'])) hidden @endif>Featured image or global social image</div>
                <div class="content-seo-social-copy">
                    <div class="content-seo-social-url" data-seo-social-url>{{ $preview['effectiveCanonical'] ?? '' }}</div>
                    <div class="content-seo-social-title" data-seo-social-title>{{ $preview['effectiveTitle'] ?? '' }}</div>
                    <div class="content-seo-social-description" data-seo-social-description>{{ $preview['effectiveDescription'] ?? '' }}</div>
                </div>
            </div>
            <p class="content-seo-note">Preview only. Search engines and social networks may display the content differently.</p>
        </div>
    </div>

    <script>
        (function () {
            const root = document.currentScript?.closest('.content-seo-panel') || document.querySelector('[data-content-seo]');
            if (!root || root.dataset.seoInitialized === '1') return;
            root.dataset.seoInitialized = '1';

            const preview = JSON.parse(root.dataset.seoPreview || '{}');
            const selectors = JSON.parse(root.dataset.seoSelectors || '{}');
            const titleInput = root.querySelector('[data-seo-title]');
            const descriptionInput = root.querySelector('[data-seo-description]');
            const canonicalInput = root.querySelector('input[name="canonical_url"]');
            const googleTitle = root.querySelector('[data-seo-google-title]');
            const googleUrl = root.querySelector('[data-seo-google-url]');
            const googleDescription = root.querySelector('[data-seo-google-description]');
            const socialTitle = root.querySelector('[data-seo-social-title]');
            const socialUrl = root.querySelector('[data-seo-social-url]');
            const socialDescription = root.querySelector('[data-seo-social-description]');
            const socialImage = root.querySelector('[data-seo-social-image]');
            const socialPlaceholder = root.querySelector('[data-seo-social-placeholder]');

            const fieldValue = (selector) => {
                if (!selector) return '';
                const field = document.querySelector(selector);
                return field ? String(field.value || '').trim() : '';
            };
            const contentImage = () => {
                if (!selectors.image) return '';
                const image = document.querySelector(selectors.image);
                return image && image.src ? image.src : '';
            };
            const generatedTitle = (contentTitle) => {
                const site = preview.siteName || '';
                const title = contentTitle || site;
                if (!title || !preview.titleTemplate) return title;
                if (site && title.includes(site)) return title;
                return preview.titleTemplate.replaceAll('{title}', title).replaceAll('{site_name}', site).trim();
            };
            const update = () => {
                const contentTitle = fieldValue(selectors.title);
                const contentDescription = fieldValue(selectors.description);
                const title = (titleInput?.value || '').trim() || generatedTitle(contentTitle);
                const description = (descriptionInput?.value || '').trim() || contentDescription || preview.defaultDescription || '';
                const url = (canonicalInput?.value || '').trim() || preview.contentUrl || '';
                const image = contentImage() || preview.defaultImage || '';

                googleTitle.textContent = title;
                googleUrl.textContent = url;
                googleDescription.textContent = description;
                socialTitle.textContent = title;
                socialUrl.textContent = url;
                socialDescription.textContent = description;
                if (image) {
                    socialImage.src = image;
                    socialImage.hidden = false;
                    socialPlaceholder.hidden = true;
                } else {
                    socialImage.removeAttribute('src');
                    socialImage.hidden = true;
                    socialPlaceholder.hidden = false;
                }
            };

            const watched = [
                titleInput, descriptionInput, canonicalInput,
                selectors.title, selectors.description, selectors.slug,
            ].filter(Boolean).map((item) => typeof item === 'string' ? document.querySelector(item) : item).filter(Boolean);
            [...new Set(watched)].forEach((field) => field.addEventListener('input', update));

            if (selectors.image) {
                const image = document.querySelector(selectors.image);
                const watchRoot = (selectors.imageWatch
                    ? document.querySelector(selectors.imageWatch)
                    : image?.parentElement) || document.body;

                if (watchRoot) {
                    const imageChanged = (mutation) => {
                        if (mutation.target === socialImage) {
                            return false;
                        }

                        if (mutation.type === 'attributes') {
                            return mutation.target.matches?.(selectors.image) === true;
                        }

                        return [...mutation.addedNodes].some((node) => node.nodeType === 1
                            && (node.matches?.(selectors.image) || node.querySelector?.(selectors.image)));
                    };

                    new MutationObserver((mutations) => {
                        if (mutations.some(imageChanged)) {
                            update();
                        }
                    }).observe(watchRoot, {
                        attributes: true,
                        attributeFilter: ['src'],
                        childList: true,
                        subtree: true,
                    });
                }
            }

            update();
        })();
    </script>
</section>
