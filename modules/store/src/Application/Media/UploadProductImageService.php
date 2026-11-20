<?php

namespace Modules\Store\Application\Media;

use Modules\Store\Domain\Media\MediaRole;
use Modules\Store\Domain\Media\MediaSource;
use Modules\Store\Domain\Media\MediaUploadCandidate;
use Modules\Store\Domain\Media\MediaUploadValidationService;
use Modules\Store\Domain\Media\ProductMediaRepository;
use Modules\Store\Domain\Media\ProductMediaStorage;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;
use Throwable;

final readonly class UploadProductImageService
{
    public function __construct(
        private MediaUploadValidationService $validation,
        private ProductMediaRepository $media,
        private ProductMediaStorage $storage,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function upload(
        string $productPublicId,
        ?string $variantPublicId,
        string $temporaryPath,
        MediaUploadCandidate $candidate,
        MediaRole $role,
        bool $primary,
        int $position,
        ActorContext $actor,
        ?string $locale = null,
        ?string $altText = null,
    ): string {
        $this->validation->validate($candidate);
        $checksum = hash_file('sha256', $temporaryPath);
        if ($checksum === false) {
            throw new \RuntimeException('The product image checksum could not be calculated.');
        }
        $assetPublicId = (string) $this->ids->generate();
        $associationPublicId = (string) $this->ids->generate();
        $extension = strtolower(pathinfo($candidate->originalFilename, PATHINFO_EXTENSION));
        $path = 'store/products/'.$productPublicId.'/'.$assetPublicId.'.'.$extension;
        $this->storage->put($temporaryPath, $path);

        try {
            $this->transactions->run(function () use (
                $assetPublicId, $associationPublicId, $productPublicId, $variantPublicId,
                $candidate, $checksum, $path, $role, $primary, $position, $actor, $locale, $altText,
            ): void {
                $now = $this->clock->now();
                $this->media->createAsset($assetPublicId, 'public', $path, $candidate, $checksum, $actor, $now);
                $this->media->attach(
                    $associationPublicId,
                    $productPublicId,
                    $variantPublicId,
                    new MediaSource(assetPublicId: $assetPublicId),
                    $role,
                    $primary,
                    max(0, $position),
                    $now,
                );
                if ($locale !== null) {
                    $this->media->addAssociationTranslation($associationPublicId, $locale, $altText, null, null, $now);
                }
            });
        } catch (Throwable $exception) {
            $this->storage->delete($path);
            throw $exception;
        }

        return $associationPublicId;
    }
}
