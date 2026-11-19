<?php

namespace App\Platform\Core\Seo;

/**
 * Robots directives with "most restrictive wins" merge semantics.
 */
final class Robots
{
    /**
     * @param  array<int, string>  $directives
     */
    public function __construct(
        public bool $index = true,
        public bool $follow = true,
        public ?int $maxSnippet = null,
        public ?string $maxImagePreview = null,
        public ?int $maxVideoPreview = null,
        public array $directives = [],
    ) {
    }

    public static function indexFollow(): self
    {
        return new self();
    }

    public static function noindex(): self
    {
        return new self(index: false, follow: true);
    }

    public function merge(self $other): self
    {
        return new self(
            index: $this->index && $other->index,
            follow: $this->follow && $other->follow,
            maxSnippet: $this->mostRestrictiveNumber($this->maxSnippet, $other->maxSnippet),
            maxImagePreview: $this->mostRestrictiveImagePreview($this->maxImagePreview, $other->maxImagePreview),
            maxVideoPreview: $this->mostRestrictiveNumber($this->maxVideoPreview, $other->maxVideoPreview),
            directives: array_values(array_unique(array_merge($this->directives, $other->directives))),
        );
    }

    public function isIndexable(): bool
    {
        return $this->index;
    }

    public function toMeta(): string
    {
        $parts = [
            $this->index ? 'index' : 'noindex',
            $this->follow ? 'follow' : 'nofollow',
        ];

        if ($this->maxSnippet !== null) {
            $parts[] = 'max-snippet:'.$this->maxSnippet;
        }

        if ($this->maxImagePreview !== null) {
            $parts[] = 'max-image-preview:'.$this->maxImagePreview;
        }

        if ($this->maxVideoPreview !== null) {
            $parts[] = 'max-video-preview:'.$this->maxVideoPreview;
        }

        foreach ($this->directives as $directive) {
            $directive = trim((string) $directive);

            if ($directive !== '') {
                $parts[] = $directive;
            }
        }

        return implode(', ', array_values(array_unique($parts)));
    }

    private function mostRestrictiveNumber(?int $a, ?int $b): ?int
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        if ($a === -1) {
            return $b;
        }

        if ($b === -1) {
            return $a;
        }

        return min($a, $b);
    }

    private function mostRestrictiveImagePreview(?string $a, ?string $b): ?string
    {
        $rank = ['none' => 0, 'standard' => 1, 'large' => 2];

        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return ($rank[$a] ?? 1) <= ($rank[$b] ?? 1) ? $a : $b;
    }
}
