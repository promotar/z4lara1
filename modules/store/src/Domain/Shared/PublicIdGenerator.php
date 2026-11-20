<?php

namespace Modules\Store\Domain\Shared;

interface PublicIdGenerator
{
    public function generate(): PublicId;
}
