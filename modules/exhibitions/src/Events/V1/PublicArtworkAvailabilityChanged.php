<?php

namespace Modules\Exhibition\Events\V1;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final readonly class PublicArtworkAvailabilityChanged implements ShouldBroadcastNow
{
    /** @param list<array<string, mixed>> $items */
    public function __construct(
        public string $exhibitionId,
        public int $version,
        public array $items,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('exhibitions.ve03.'.$this->exhibitionId)];
    }

    public function broadcastAs(): string
    {
        return 'artwork.availability.changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'event' => 'artwork.availability.changed',
            'exhibition_id' => $this->exhibitionId,
            'version' => $this->version,
            'items' => $this->items,
        ];
    }
}
