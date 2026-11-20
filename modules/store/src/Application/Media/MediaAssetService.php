<?php

namespace Modules\Store\Application\Media;

use InvalidArgumentException;
use Modules\Store\Domain\Media\MediaUploadCandidate;
use Modules\Store\Domain\Media\MediaUploadValidationService;
use Modules\Store\Domain\Media\ProductMediaRepository;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Clock;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Modules\Store\Domain\Shared\TransactionManager;

final readonly class MediaAssetService
{
    public function __construct(
        private MediaUploadValidationService $validation,
        private ProductMediaRepository $media,
        private PublicIdGenerator $ids,
        private Clock $clock,
        private TransactionManager $transactions,
    ) {}

    public function register(
        MediaUploadCandidate $candidate,
        string $disk,
        string $path,
        string $sha256,
        ActorContext $actor,
    ): string {
        $this->validation->validate($candidate);
        $disk = trim($disk);
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if (! preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $disk)
            || $path === ''
            || strlen($path) > 512
            || str_contains('/'.$path.'/', '/../')
            || ! preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            throw new InvalidArgumentException('Media disk, path, or SHA-256 checksum is invalid.');
        }

        $publicId = (string) $this->ids->generate();
        $now = $this->clock->now();
        $this->transactions->run(fn (): mixed => $this->media->createAsset(
            $publicId,
            $disk,
            $path,
            $candidate,
            $sha256,
            $actor,
            $now,
        ));

        return $publicId;
    }
}
