<?php

namespace Modules\PageBuilder;

class VvvebDocument
{
    public function fromPage(object $page): string
    {
        $document = trim((string) ($page->vvvebjs_html ?? ''));

        if ($document !== '') {
            return $this->normalize($document, (string) ($page->title ?? 'Page'));
        }

        $body = (string) ($page->html ?: $page->content ?: '');
        $css = $this->sanitizeCss((string) ($page->css ?? ''));

        return $this->blank((string) ($page->title ?? 'Page'), $body, $css);
    }

    public function blank(string $title, string $body = '', string $css = ''): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = $this->sanitizeFragment($body);

        if (trim($body) === '') {
            $body = '<main class="container py-5"><section class="py-5 text-center"><h1>'.$safeTitle.'</h1><p>Drag VvvebJs elements here to start building.</p></section></main>';
        }

        return '<!DOCTYPE html>\n<html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .'<base href="/page-builder-assets/demo/landing/">'
            .'<title>'.$safeTitle.'</title>'
            .'<link id="landing-css" href="css/style.bundle.css" rel="stylesheet">'
            .'<link id="vvvebjs-css" href="css/custom.css" rel="stylesheet">'
            .'<style>'.$this->sanitizeCss($css).'</style></head><body class="page">'
            .$body.'</body></html>';
    }

    public function normalize(string $document, string $title = 'Page'): string
    {
        $document = str_replace(['<?php', '<?=', '@php', '@endphp'], '', $document);
        $document = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $document) ?? $document;
        $document = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $document) ?? $document;
        $document = preg_replace('/(href|src|formaction)\s*=\s*(["\'])\s*(?:javascript|vbscript):.*?\2/is', '$1="#"', $document) ?? $document;
        $document = preg_replace_callback(
            '/<style\b([^>]*)>(.*?)<\/style>/is',
            fn (array $matches): string => '<style'.$matches[1].'>'.$this->sanitizeCss((string) $matches[2]).'</style>',
            $document
        ) ?? $document;

        if (! preg_match('/<html\b/i', $document)) {
            return $this->blank($title, $document);
        }

        if (! str_contains(strtolower($document), '<!doctype')) {
            $document = "<!DOCTYPE html>\n".$document;
        }

        if (preg_match('/<base\b[^>]*>/i', $document)) {
            $document = preg_replace('/<base\b[^>]*>/i', '<base href="/page-builder-assets/demo/landing/">', $document, 1) ?? $document;
        } else {
            $document = preg_replace('/<head\b[^>]*>/i', '$0<base href="/page-builder-assets/demo/landing/">', $document, 1) ?? $document;
        }

        return trim($document);
    }

    public function body(string $document): string
    {
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $document, $matches) === 1) {
            return $this->sanitizeFragment((string) $matches[1]);
        }

        return $this->sanitizeFragment($document);
    }

    public function css(string $document): string
    {
        preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $document, $matches);

        return $this->sanitizeCss(implode("\n", $matches[1] ?? []));
    }

    public function sanitizeFragment(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*(["\']).*?\1/is', '', $html) ?? $html;
        $html = preg_replace('/(href|src|formaction)\s*=\s*(["\'])\s*(?:javascript|vbscript|data):.*?\2/is', '$1="#"', $html) ?? $html;

        return trim($html);
    }

    public function sanitizeCss(string $css): string
    {
        $css = preg_replace('/@import\b[^;]+;/i', '', $css) ?? $css;
        $css = preg_replace('/expression\s*\([^)]*\)/i', '', $css) ?? $css;
        $css = preg_replace('/(?:javascript|vbscript)\s*:/i', '', $css) ?? $css;
        $css = preg_replace('/(?:behavior|-moz-binding)\s*:/i', 'blocked:', $css) ?? $css;

        return trim($css);
    }
}
