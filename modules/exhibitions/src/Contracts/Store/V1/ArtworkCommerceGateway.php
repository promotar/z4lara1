<?php

namespace Modules\Exhibition\Contracts\Store\V1;

interface ArtworkCommerceGateway
{
    public function status(string $artworkPublicId): ArtworkCommerceStatus;
}
