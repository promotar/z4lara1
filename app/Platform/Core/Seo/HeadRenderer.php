<?php

namespace App\Platform\Core\Seo;

/**
 * Renders the final Core-owned head tags.
 *
 * Canonical/hreflang remain opt-in while legacy modules still provide their
 * own head blocks; enable them once all modules use SeoContributor.
 */
final class HeadRenderer
{
    public function render(SeoData $seo): string
    {
        $tags = [];

        if ($seo->title !== null && $seo->title !== '') {
            $tags[] = '<title>'.$this->e($seo->title).'</title>';
        }

        if ($seo->description !== null && $seo->description !== '') {
            $tags[] = '<meta name="description" content="'.$this->e($seo->description).'">';
        }

        $tags[] = '<meta name="robots" content="'.$this->e($seo->robots->toMeta()).'">';

        if (config('seo.head.canonical', false) && $seo->canonical) {
            $tags[] = '<link rel="canonical" href="'.$this->e($seo->canonical).'">';
        }

        if (config('seo.head.alternates', false)) {
            foreach ($seo->alternates as $hreflang => $href) {
                $tags[] = '<link rel="alternate" hreflang="'.$this->e((string) $hreflang).'" href="'.$this->e((string) $href).'">';
            }
        }

        foreach ($seo->meta as $name => $content) {
            if (trim((string) $content) !== '') {
                $tags[] = '<meta name="'.$this->e((string) $name).'" content="'.$this->e((string) $content).'">';
            }
        }

        foreach ($seo->links as $rel => $href) {
            if (trim((string) $href) !== '') {
                $tags[] = '<link rel="'.$this->e((string) $rel).'" href="'.$this->e((string) $href).'">';
            }
        }

        if ($seo->ogEnabled) {
            $tags[] = '<meta property="og:type" content="'.$this->e($seo->ogType).'">';

            if ($seo->siteName) {
                $tags[] = '<meta property="og:site_name" content="'.$this->e($seo->siteName).'">';
            }

            if ($seo->ogTitle) {
                $tags[] = '<meta property="og:title" content="'.$this->e($seo->ogTitle).'">';
            }

            if ($seo->ogDescription) {
                $tags[] = '<meta property="og:description" content="'.$this->e($seo->ogDescription).'">';
            }

            if ($seo->canonical) {
                $tags[] = '<meta property="og:url" content="'.$this->e($seo->canonical).'">';
            }

            if ($seo->image) {
                $tags[] = '<meta property="og:image" content="'.$this->e($seo->image).'">';
            }

            if ($seo->twitterCard) {
                $tags[] = '<meta name="twitter:card" content="'.$this->e($seo->twitterCard).'">';
            }

            if ($seo->twitterTitle) {
                $tags[] = '<meta name="twitter:title" content="'.$this->e($seo->twitterTitle).'">';
            }

            if ($seo->twitterDescription) {
                $tags[] = '<meta name="twitter:description" content="'.$this->e($seo->twitterDescription).'">';
            }

            if ($seo->twitterImage) {
                $tags[] = '<meta name="twitter:image" content="'.$this->e($seo->twitterImage).'">';
            }
        }

        foreach ($seo->schema as $schema) {
            if (! is_array($schema) || $schema === []) {
                continue;
            }

            $encoded = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (is_string($encoded)) {
                $tags[] = '<script type="application/ld+json">'.$encoded.'</script>';
            }
        }

        return implode("\n        ", $tags);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }
}
