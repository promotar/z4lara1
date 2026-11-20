<?php

namespace Modules\Store\Infrastructure\Identity;

use Modules\Store\Domain\Shared\PublicId;
use Modules\Store\Domain\Shared\PublicIdGenerator;
use Symfony\Component\Uid\Ulid;

final class UlidPublicIdGenerator implements PublicIdGenerator
{
    public function generate(): PublicId
    {
        return new PublicId((string) new Ulid);
    }
}
