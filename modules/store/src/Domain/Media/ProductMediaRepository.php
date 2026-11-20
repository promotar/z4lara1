<?php

namespace Modules\Store\Domain\Media;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;

interface ProductMediaRepository
{
    public function createAsset(
        string $publicId,
        string $disk,
        string $path,
        MediaUploadCandidate $candidate,
        string $checksum,
        ActorContext $actor,
        DateTimeImmutable $now,
    ): void;

    public function variantBelongsToProduct(string $variantPublicId, string $productPublicId): bool;

    public function associationBelongsToProduct(string $associationPublicId, string $productPublicId): bool;

    public function attach(
        string $associationPublicId,
        string $productPublicId,
        ?string $variantPublicId,
        MediaSource $source,
        MediaRole $role,
        bool $primary,
        int $position,
        DateTimeImmutable $now,
    ): void;

    public function addAssociationTranslation(
        string $associationPublicId,
        string $locale,
        ?string $altText,
        ?string $title,
        ?string $caption,
        DateTimeImmutable $now,
    ): void;

    public function removeAssociation(string $associationPublicId): ?int;

    public function assetReferenceCount(int $assetId): int;

    /** @param list<string> $associationPublicIds */
    public function reorderAssociations(
        string $productPublicId,
        array $associationPublicIds,
        DateTimeImmutable $now,
    ): void;
}
