<!DOCTYPE html>
{{-- Public rendering is owned by the VvvebJs builder plugin. --}}
@php
    $vvvebRenderer = app(\Modules\PageBuilder\ThemeCompositionService::class);
    $vvvebView = $vvvebRenderer->pageViewData($page, (bool) ($isPreview ?? false));
    extract($vvvebView, EXTR_SKIP);
    $siteLanguage = ($platformSettings['general.site_language'] ?? 'ar') === 'en' ? 'en' : 'ar';
    $htmlDirection = $siteLanguage === 'ar' ? 'rtl' : 'ltr';
@endphp
<html lang="{{ $siteLanguage }}" dir="{{ $htmlDirection }}">
    <head>
        <script>
            // Phone-preview extensions render the site inside an iframe while keeping
            // a desktop viewport. Mark that context so the card layout can remain
            // genuinely mobile instead of being scaled down.
            if (window.self !== window.top) {
                document.documentElement.classList.add('art-device-preview');
            }
        </script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} · {{ $siteTitle }}</title>
        @if ($description)
            <meta name="description" content="{{ $description }}">
        @endif
        @if ($siteIcon)
            <link rel="icon" href="{{ $siteIcon }}">
        @else
            <link rel="icon" href="data:,">
        @endif
        @include('platform.plugin-assets', ['scope' => 'frontend', 'kind' => 'styles'])
        <link rel="stylesheet" href="{{ url('/page-builder-assets/demo/landing/css/style.bundle.css') }}">
        <link rel="stylesheet" href="{{ url('/page-builder-assets/demo/landing/css/custom.css') }}">
        <style>
            html,
            body {
                margin: 0;
                min-height: 100%;
            }

            body {
                font-family: Figtree, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: #111827;
                background: #ffffff;
            }

            img {
                max-width: 100%;
            }

            .pb-image-action {
                display: inline-block;
                line-height: 0;
            }

            .pb-image-lightbox {
                cursor: zoom-in;
            }

            .pb-lightbox {
                position: fixed;
                inset: 0;
                z-index: 9999;
                display: none;
                align-items: center;
                justify-content: center;
                padding: 24px;
                background: rgba(15, 23, 42, 0.88);
            }

            .pb-lightbox.is-open {
                display: flex;
            }

            .pb-lightbox img {
                max-width: min(100%, 1180px);
                max-height: calc(100vh - 72px);
                object-fit: contain;
                background: #fff;
            }

            .pb-lightbox[data-size="full"] img {
                width: min(100%, 1400px);
            }

            .pb-lightbox button {
                position: absolute;
                top: 16px;
                right: 16px;
                width: 40px;
                height: 40px;
                border: 0;
                border-radius: 6px;
                background: #ffffff;
                color: #111827;
                font-size: 24px;
                line-height: 1;
                cursor: pointer;
            }

            {!! $pageCss !!}
            {!! $dynamicLayoutCss !!}

            /* Latest is a direct News link on phone layouts; the mega menu is desktop-only. */
            @media (max-width: 768px), (max-device-width: 768px) {
                .art-header-latest-menu .art-header-mega {
                    display: none !important;
                    pointer-events: none !important;
                }

                .art-header-latest::after {
                    display: none !important;
                }
            }

            html.art-device-preview .art-header-latest-menu .art-header-mega {
                display: none !important;
                pointer-events: none !important;
            }

            html.art-device-preview .art-header-latest::after {
                display: none !important;
            }
        </style>
    </head>
    <body>
        @if ($isPreview ?? false)
            <div style="background:#111827;color:#fff;font:14px system-ui;padding:10px 16px;">
                Preview: {{ $page->title }} · {{ ucfirst($page->status) }}
            </div>
        @endif

        @foreach ($dynamicHeaders as $dynamicHeader)
            <div data-platform-content-type="header" data-platform-content-id="{{ $dynamicHeader->id }}">
                {!! $dynamicHeader->rendered_html !!}
            </div>
        @endforeach

        {!! $pageHtml !!}

        @foreach ($dynamicFooters as $dynamicFooter)
            <div data-platform-content-type="footer" data-platform-content-id="{{ $dynamicFooter->id }}">
                {!! $dynamicFooter->rendered_html !!}
            </div>
        @endforeach

        <div class="pb-lightbox" data-size="contain" aria-hidden="true">
            <button type="button" aria-label="Close lightbox">&times;</button>
            <img src="" alt="">
        </div>

        <script>
            (() => {
                const lightbox = document.querySelector('.pb-lightbox');

                if (!lightbox) {
                    return;
                }

                const image = lightbox.querySelector('img');
                const closeButton = lightbox.querySelector('button');

                const close = () => {
                    lightbox.classList.remove('is-open');
                    lightbox.setAttribute('aria-hidden', 'true');
                    image.setAttribute('src', '');
                    image.setAttribute('alt', '');
                };

                document.addEventListener('click', event => {
                    const trigger = event.target.closest('[data-pb-lightbox-trigger="image"]');

                    if (!trigger) {
                        return;
                    }

                    event.preventDefault();
                    const sourceImage = trigger.querySelector('img');
                    const href = trigger.getAttribute('href') || (sourceImage ? sourceImage.getAttribute('src') : '');

                    if (!href) {
                        return;
                    }

                    image.setAttribute('src', href);
                    image.setAttribute('alt', sourceImage ? (sourceImage.getAttribute('alt') || '') : '');
                    lightbox.dataset.size = trigger.dataset.pbLightboxSize || 'contain';
                    lightbox.classList.add('is-open');
                    lightbox.setAttribute('aria-hidden', 'false');
                });

                lightbox.addEventListener('click', event => {
                    if (event.target === lightbox) {
                        close();
                    }
                });

                closeButton.addEventListener('click', close);
                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && lightbox.classList.contains('is-open')) {
                        close();
                    }
                });
            })();
        </script>
        @include('platform.plugin-assets', ['scope' => 'frontend', 'kind' => 'scripts'])
    </body>
</html>
