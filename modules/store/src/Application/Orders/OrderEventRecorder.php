<?php

namespace Modules\Store\Application\Orders;

use Illuminate\Support\Facades\DB;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\PublicIdGenerator;

final readonly class OrderEventRecorder
{
    public function __construct(private PublicIdGenerator $ids) {}

    /** @param array<string, mixed> $metadata */
    public function record(
        int $orderId,
        string $eventType,
        ActorContext $actor,
        ?string $message = null,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        array $metadata = [],
        string $visibility = 'internal',
        ?string $actorName = null,
    ): void {
        DB::table('store_order_events')->insert([
            'public_id' => (string) $this->ids->generate(),
            'order_id' => $orderId,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'actor_name' => $actorName ?? $actor->name,
            'visibility' => $visibility,
            'message' => $message,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
