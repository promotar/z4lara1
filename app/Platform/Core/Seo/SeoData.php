<?php

namespace App\Platform\Core\Seo;

/**
 * Mutable SEO data object assembled by the Core pipeline.
 *
 * The object is intentionally mutable while contributors run; renderers
 * should treat it as read-only after SeoManager returns.
 */
final class SeoData
{
    public ?string $title = null;

    public ?string $siteName = null;

    public ?string $description = null;

    public ?string $canonical = null;

    public Robots $robots;

    /** @var array<string, string> */
    public array $alternates = [];

    public ?string $image = null;

    public ?string $imageAlt = null;

    public string $ogType = 'website';

    public bool $ogEnabled = true;

    public ?string $ogTitle = null;

    public ?string $ogDescription = null;

    public ?string $twitterCard = null;

    public ?string $twitterTitle = null;

    public ?string $twitterDescription = null;

    public ?string $twitterImage = null;

    /** @var array<int, array<string, mixed>> */
    public array $schema = [];

    /** @var array<string, string> */
    public array $meta = [];

    /** @var array<string, string> */
    public array $links = [];

    /** @var array<string, mixed> */
    public array $extra = [];

    public function __construct(?Robots $robots = null)
    {
        $this->robots = $robots ?? new Robots();
    }
}
