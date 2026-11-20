<?php

namespace Modules\Store\Application\Artwork;

use DomainException;
use Illuminate\Support\Facades\DB;

final class ArtworkPublicationGate
{
    public function assertEligible(string $productPublicId): void
    {
        $record = DB::table('store_products as product')
            ->leftJoin('store_artwork_details as artwork', 'artwork.product_id', '=', 'product.id')
            ->where('product.public_id', $productPublicId)
            ->first(['product.type_code', 'artwork.workflow_state']);

        if ($record === null || $record->type_code !== 'artwork') {
            return;
        }

        if ($record->workflow_state !== 'approved') {
            throw new DomainException('Artwork products must pass Store artwork review before publication.');
        }
    }
}
