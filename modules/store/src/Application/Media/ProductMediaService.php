<?php

namespace Modules\Store\Application\Media;

use InvalidArgumentException;
use Modules\Store\Domain\Media\MediaRole;
use Modules\Store\Domain\Media\MediaSource;
use Modules\Store\Domain\Media\ProductMediaRepository;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class ProductMediaService
{
    public function __construct(
        private ProductMediaRepository $media,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function attach(
        string $productPublicId,
        ?string $variantPublicId,
        MediaSource $source,
        MediaRole $role,
        bool $primary = false,
        int $position = 0,
        ?string $locale = null,
        ?string $altText = null,
        ?string $title = null,
        ?string $caption = null,
    ): string {
        if ($variantPublicId !== null && ! $this->media->variantBelongsToProduct($variantPublicId, $productPublicId)) {
            throw new InvalidArgumentException('Variant media must belong to the same product.');
        }

        $publicId = (string) $this->ids->generate();
        $now = $this->clock->now();

        $this->transactions->run(function () use (
            $publicId, $productPublicId, $variantPublicId, $source, $role, $primary,
            $position, $locale, $altText, $title, $caption, $now,
        ): void {
            $this->media->attach(
                associationPublicId: $publicId,
                productPublicId: $productPublicId,
                variantPublicId: $variantPublicId,
                source: $source,
                role: $role,
                primary: $primary,
                position: max(0, $position),
                now: $now,
            );
            if ($locale !== null) {
                $this->media->addAssociationTranslation($publicId, $locale, $altText, $title, $caption, $now);
            }
        });

        return $publicId;
    }

    public function remove(string $productPublicId, string $associationPublicId): bool
    {
        if (! $this->media->associationBelongsToProduct($associationPublicId, $productPublicId)) {
            throw new InvalidArgumentException('Media association does not belong to the selected product.');
        }

        return $this->transactions->run(function () use ($associationPublicId): bool {
            $assetId = $this->media->removeAssociation($associationPublicId);

            return $assetId !== null && $this->media->assetReferenceCount($assetId) === 0;
        });
    }

    /** @param list<string> $associationPublicIds */
    public function reorder(string $productPublicId, array $associationPublicIds): void
    {
        if ($associationPublicIds === [] || count($associationPublicIds) !== count(array_unique($associationPublicIds))) {
            throw new InvalidArgumentException('Media order must contain unique media items.');
        }

        $this->transactions->run(function () use ($productPublicId, $associationPublicIds): void {
            $this->media->reorderAssociations(
                $productPublicId,
                array_values($associationPublicIds),
                $this->clock->now(),
            );
        });
    }
}
