<?php

namespace Modules\Exhibition\Application\Readiness;

use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Contracts\Readiness\ExhibitionReadiness;
use Modules\Exhibition\Contracts\Readiness\ExhibitionReadinessData;
use Modules\Exhibition\Contracts\Store\V1\StoreLifecycleGateway;

final class DefaultExhibitionReadiness implements ExhibitionReadiness
{
    public function status(): ExhibitionReadinessData
    {
        $identity = Schema::hasTable('exhibition_artist_identities');
        $audit = Schema::hasTable('exhibition_audit_entries');
        $integration = Schema::hasTable('exhibition_store_lifecycle_receipts')
            && Schema::hasTable('exhibition_integration_cursors');
        $publicationContract = interface_exists('Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableGateway')
            && app()->bound('Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableGateway');
        $lifecycleContract = app()->bound(StoreLifecycleGateway::class)
            && app(StoreLifecycleGateway::class)->available();
        $storeGateway = $publicationContract && $lifecycleContract;

        return new ExhibitionReadinessData(
            available: $identity && $audit && $integration && $storeGateway,
            version: (string) config('exhibitions.version', '1.6.7'),
            artistIdentityTableAvailable: $identity,
            auditTableAvailable: $audit,
            coreIdentityIsExternal: true,
            storeGatewayBound: $storeGateway,
            checks: [
                $identity ? 'Artist identity mapping is ready.' : 'Artist identity mapping table is missing.',
                $audit ? 'Module audit trail is ready.' : 'Module audit table is missing.',
                $integration ? 'Durable Store lifecycle receipts and cursor are ready.' : 'Store lifecycle persistence is missing.',
                'Core remains the user and authentication source of truth.',
                $storeGateway
                    ? 'Store publication, lifecycle feed, and customer order ownership contracts are available.'
                    : 'One or more required Store v12.2 integration contracts are unavailable.',
            ],
        );
    }
}
