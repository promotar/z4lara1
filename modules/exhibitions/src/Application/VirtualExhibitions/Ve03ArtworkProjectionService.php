<?php

namespace Modules\Exhibition\Application\VirtualExhibitions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Exhibition\Application\Inventory\ArtworkEffectiveAvailabilityService;
use Modules\Exhibition\Application\Media\Ve03ArtworkDerivativeService;
use Modules\Exhibition\Contracts\Store\V1\ArtworkCommerceGateway;

final readonly class Ve03ArtworkProjectionService
{
    public function __construct(
        private ArtworkCommerceGateway $commerce,
        private Ve03ArtworkDerivativeService $derivatives,
        private ArtworkEffectiveAvailabilityService $availability,
    ) {}

    /** @return list<array<string, mixed>> */
    public function publicPlacements(object $room, object $version): array
    {
        return array_map(function (array $projection): array {
            unset($projection['_artwork_id'], $projection['_media_id']);

            return $projection;
        }, $this->project($room, $version));
    }

    /** @return array{version:int,items:list<array<string,mixed>>} */
    public function availability(object $room, object $version): array
    {
        $placements = $this->project($room, $version);
        $items = array_map(static function (array $placement): array {
            $artwork = (array) ($placement['artwork'] ?? []);

            return [
                'placement_id' => (string) ($placement['id'] ?? ''),
                'artwork_id' => (string) ($artwork['id'] ?? ''),
                'availability' => strtolower((string) ($artwork['availability'] ?? 'unavailable')),
                'sold' => (bool) ($artwork['sold'] ?? false),
                'interactive' => (bool) ($placement['interactive'] ?? false),
            ];
        }, $placements);

        return [
            'version' => (int) sprintf('%u', crc32(json_encode($items, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))),
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    public function popup(object $room, object $version, string $placementPublicId): array
    {
        $projection = $this->findProjection($room, $version, $placementPublicId);
        if (! ($projection['interactive'] ?? false)) {
            throw new DomainException('This artwork is not available for interaction.');
        }
        $artwork = DB::table('exhibition_artworks')->where('id', $projection['_artwork_id'])->where('state', 'published')->first();
        if (! $artwork) {
            throw new DomainException('The published artwork is unavailable.');
        }
        $artist = DB::table('exhibition_artist_identities as identity')
            ->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')
            ->where('identity.id', $artwork->artist_identity_id)
            ->orderByDesc('application.updated_at')
            ->select('application.artistic_name', 'application.legal_name', 'application.biography', 'application.country')
            ->first();
        $authenticity = DB::table('exhibition_artwork_authenticity')->where('artwork_id', $artwork->id)->first();

        $details = array_filter([
            'Physical dimensions' => $this->dimensionLabel($artwork),
            'Medium' => $this->joinValues([$artwork->material, $artwork->surface]),
            'Technique' => $artwork->technique,
            'Year' => $artwork->completion_year,
            'Edition' => $this->editionLabel($artwork),
            'Certificate / authenticity' => $this->authenticityLabel($authenticity),
            'Collection' => $artwork->collection_name,
            'Description' => $artwork->detailed_description,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $artistFields = array_filter([
            'Artist' => $artist?->artistic_name ?: $artist?->legal_name,
            'Country' => $artist?->country,
            'Biography' => $artist?->biography,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $delivery = array_filter([
            'Preparation' => $artwork->preparation_days ? $artwork->preparation_days.' days' : null,
            'Packaging and shipping' => $artwork->shipping_notes,
            'Certificate delivery' => $this->authenticityLabel($authenticity),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $artworkFields = array_filter([
            'Availability' => ucfirst(str_replace('_', ' ', (string) $projection['artwork']['availability'])),
            'Price' => $this->priceLabel($projection['artwork']['price']),
            'Overview' => $artwork->short_description,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return [
            'placement_id' => $placementPublicId,
            'artwork' => [
                'id' => (string) $artwork->public_id,
                'title' => (string) $artwork->title,
                'artist' => (string) ($artist?->artistic_name ?: $artist?->legal_name ?: $projection['artwork']['artist']),
                'availability' => (string) $projection['artwork']['availability'],
                'price' => $projection['artwork']['price'],
                'short_description' => (string) ($artwork->short_description ?? ''),
                'image_url' => route('exhibitions.virtual.ve03.placement-derivative', [$room->slug, $placementPublicId, 'popup']),
                'store_variant_public_id' => (string) ($projection['artwork']['store_variant_public_id'] ?? ''),
            ],
            'source' => [
                'owner_code' => 'exhibitions',
                'type' => 'virtual_exhibition',
                'reference' => $placementPublicId,
                'context' => [
                    'exhibition_id' => (string) $room->public_id,
                    'exhibition_slug' => (string) $room->slug,
                    'room_code' => (string) $projection['room_code'],
                    'slot_code' => (string) $projection['slot_code'],
                ],
            ],
            'sections' => array_filter([
                'artwork' => $artworkFields,
                'details' => $details,
                'artist' => $artistFields,
                'delivery' => $delivery,
            ], static fn (array $fields): bool => $fields !== []),
        ];
    }

    /** @return array{path:string,hash:string,size:int,disk:string} */
    public function derivative(object $room, object $version, string $placementPublicId, string $variant): array
    {
        $projection = $this->findProjection($room, $version, $placementPublicId);
        $media = DB::table('exhibition_artwork_media')->where('id', $projection['_media_id'])->where('state', 'ready')->first();
        if (! $media) {
            throw new DomainException('The artwork media is unavailable.');
        }

        return $this->derivatives->publicArtifact($media, $variant);
    }

    /** @return list<array<string, mixed>> */
    private function project(object $room, object $version): array
    {
        $slots = DB::table('exhibition_virtual_environment_slots as slot')
            ->join('exhibition_virtual_environment_rooms as environment_room', 'environment_room.id', '=', 'slot.room_id')
            ->where('slot.version_id', $version->id)
            ->where('slot.enabled_by_default', true)
            ->orderBy('environment_room.sort_order')
            ->orderBy('slot.id')
            ->get([
                'slot.*',
                'environment_room.room_code',
            ]);
        if ($slots->isEmpty()) {
            return [];
        }

        $used = [];
        $usedArtworks = [];
        $projected = [];
        if (DB::getSchemaBuilder()->hasTable('exhibition_virtual_slot_occupancies')) {
            $confirmed = DB::table('exhibition_virtual_slot_occupancies as occupancy')
                ->join('exhibition_virtual_placements as placement', 'placement.id', '=', 'occupancy.placement_id')
                ->join('exhibition_virtual_environment_slots as slot', 'slot.id', '=', 'occupancy.slot_id')
                ->join('exhibition_virtual_environment_rooms as environment_room', 'environment_room.id', '=', 'slot.room_id')
                ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
                ->join('exhibition_artwork_publications as publication', 'publication.artwork_id', '=', 'artwork.id')
                ->join('exhibition_artwork_media as media', function ($join): void {
                    $join->on('media.artwork_id', '=', 'artwork.id')->where('media.state', 'ready')->where('media.is_primary', true);
                })
                ->where('occupancy.version_id', $version->id)
                ->whereIn('placement.state', ['active', 'sold'])
                ->where('artwork.state', 'published')
                ->orderBy('placement.id')
                ->get(['placement.public_id as placement_public_id', 'placement.state as placement_state', 'placement.sold_at', 'placement.metadata as placement_metadata', 'artwork.*', 'media.id as media_id', 'publication.store_variant_public_id', 'slot.id as exact_slot_id', 'slot.slot_code as exact_slot_code', 'slot.translation as exact_translation', 'slot.rotation as exact_rotation', 'slot.metadata as exact_slot_metadata', 'environment_room.room_code']);

            foreach ($confirmed as $placement) {
                if (isset($usedArtworks[(int) $placement->id])) {
                    continue;
                }
                // Preserve the occupied slot, but never render an artwork at
                // an invented size when its physical dimensions are missing.
                $used[(int) $placement->exact_slot_id] = true;
                $usedArtworks[(int) $placement->id] = true;
                $dimensions = Ve03ArtworkDimensions::tryFromArtwork($placement);
                if ($dimensions === null) {
                    continue;
                }
                $width = $dimensions->widthMeters;
                $height = $dimensions->heightMeters;
                $commerce = $this->commerce->status((string) $placement->public_id);
                $inventoryAvailability = $this->availability->forArtwork((int) $placement->id, $commerce->availability);
                $sold = $placement->placement_state === 'sold'
                    || $placement->sold_at !== null
                    || in_array($inventoryAvailability, ['reserved', 'sold'], true);
                $reserved = false;
                $purchasable = $commerce->purchasable
                    && in_array($inventoryAvailability, [null, 'available'], true)
                    && ! $sold
                    && ! $reserved;
                $interactive = ! $sold;
                $availability = $purchasable ? 'Available' : ($sold ? 'Sold' : ($reserved ? 'Reserved' : 'Unavailable'));
                $publicId = (string) $placement->placement_public_id;
                $placementMetadata = json_decode((string) $placement->placement_metadata, true) ?: [];
                $slotMetadata = json_decode((string) $placement->exact_slot_metadata, true) ?: [];
                $frame = $this->frameProjection($placementMetadata['frame'] ?? null);
                $artist = DB::table('exhibition_artist_identities as identity')->join('exhibition_artist_applications as application', 'application.core_user_id', '=', 'identity.core_user_id')->where('identity.id', $placement->artist_identity_id)->orderByDesc('application.id')->value(DB::raw("coalesce(nullif(application.artistic_name, ''), application.legal_name)"));
                $projected[] = [
                    'id' => $publicId, 'slot_code' => (string) $placement->exact_slot_code, 'room_code' => (string) $placement->room_code,
                    'transform' => ['position' => $this->decodeVector((string) $placement->exact_translation, 3), 'quaternion' => $this->decodeVector((string) $placement->exact_rotation, 4), 'outward_offset_m' => 0.035],
                    'dimensions' => ['width_m' => $width, 'height_m' => $height], 'frame' => $frame,
                    'display' => $this->displayProjection($slotMetadata),
                    'interactive' => $interactive,
                    'artwork' => [
                        'id' => (string) $placement->public_id, 'title' => (string) $placement->title, 'artist' => (string) ($artist ?: 'Artist'),
                        'availability' => $availability, 'sold' => $sold,
                        'room_texture_url' => route('exhibitions.virtual.ve03.placement-derivative', [$room->slug, $publicId, 'room']),
                        'near_texture_url' => route('exhibitions.virtual.ve03.placement-derivative', [$room->slug, $publicId, 'near']),
                        'popup_url' => $interactive ? route('exhibitions.virtual.ve03.placement-popup', [$room->slug, $publicId]) : null,
                        'store_variant_public_id' => $purchasable ? (string) $placement->store_variant_public_id : null,
                        'price' => $commerce->priceMinor === null ? null : ['minor' => $commerce->priceMinor, 'scale' => 3, 'currency' => $commerce->currency],
                    ],
                    '_artwork_id' => (int) $placement->id, '_media_id' => (int) $placement->media_id,
                ];
            }
        }

        $placements = collect();
        if (! ($room->is_ve03_environment ?? false)) {
            $placements = DB::table('exhibition_virtual_placement_requests as placement')
                ->join('exhibition_artworks as artwork', 'artwork.id', '=', 'placement.artwork_id')
                ->join('exhibition_artwork_publications as publication', 'publication.artwork_id', '=', 'artwork.id')
                ->join('exhibition_artwork_media as media', function ($join): void {
                    $join->on('media.artwork_id', '=', 'artwork.id')
                        ->where('media.state', '=', 'ready')
                        ->where('media.is_primary', '=', true);
                })
                ->where('placement.room_id', $room->id)
                ->where('placement.status', 'published')
                ->where('artwork.state', 'published')
                ->where('publication.state', 'published')
                ->orderBy('placement.id')
                ->get([
                    'placement.public_id as placement_public_id',
                    'placement.artwork_snapshot',
                    'placement.updated_at as placement_updated_at',
                    'artwork.*',
                    'media.id as media_id',
                    'publication.store_variant_public_id',
                ]);
        }

        foreach ($placements as $placement) {
            if (isset($usedArtworks[(int) $placement->id])) {
                continue;
            }
            $dimensions = Ve03ArtworkDimensions::tryFromArtwork($placement);
            if ($dimensions === null) {
                $usedArtworks[(int) $placement->id] = true;
                continue;
            }
            $width = $dimensions->widthMeters;
            $height = $dimensions->heightMeters;
            $slot = $slots->first(function (object $candidate) use ($width, $height, $used): bool {
                return ! isset($used[$candidate->id]) && $this->fits($candidate, $width, $height);
            });
            if (! $slot) {
                continue;
            }
            $used[$slot->id] = true;
            $usedArtworks[(int) $placement->id] = true;
            $snapshot = json_decode((string) $placement->artwork_snapshot, true) ?: [];
            $slotMetadata = json_decode((string) $slot->metadata, true) ?: [];
            $commerce = $this->commerce->status((string) $placement->public_id);
            $inventoryAvailability = $this->availability->forArtwork((int) $placement->id, $commerce->availability);
            $sold = $placement->sold_at !== null
                || in_array($inventoryAvailability, ['reserved', 'sold'], true);
            $reserved = false;
            $purchasable = $commerce->purchasable
                && in_array($inventoryAvailability, [null, 'available'], true)
                && ! $sold
                && ! $reserved;
            $interactive = ! $sold;
            $availability = $purchasable ? 'Available' : ($sold ? 'Sold' : ($reserved ? 'Reserved' : 'Unavailable'));
            $publicId = (string) $placement->placement_public_id;
            $projected[] = [
                'id' => $publicId,
                'slot_code' => (string) $slot->slot_code,
                'room_code' => (string) $slot->room_code,
                'transform' => [
                    'position' => $this->decodeVector((string) $slot->translation, 3),
                    'quaternion' => $this->decodeVector((string) $slot->rotation, 4),
                    'outward_offset_m' => 0.035,
                ],
                'dimensions' => ['width_m' => $width, 'height_m' => $height],
                'frame' => ['style' => 'none', 'color' => null, 'backing_depth_m' => 0.025],
                'display' => $this->displayProjection($slotMetadata),
                'interactive' => $interactive,
                'artwork' => [
                    'id' => (string) $placement->public_id,
                    'title' => (string) $placement->title,
                    'artist' => (string) ($snapshot['artist'] ?? 'Artist'),
                    'availability' => $availability,
                    'sold' => $sold,
                    'room_texture_url' => route('exhibitions.virtual.ve03.placement-derivative', [$room->slug, $publicId, 'room']),
                    'near_texture_url' => route('exhibitions.virtual.ve03.placement-derivative', [$room->slug, $publicId, 'near']),
                    'popup_url' => $interactive ? route('exhibitions.virtual.ve03.placement-popup', [$room->slug, $publicId]) : null,
                    'store_variant_public_id' => $purchasable ? (string) $placement->store_variant_public_id : null,
                    'price' => $commerce->priceMinor === null ? null : [
                        'minor' => $commerce->priceMinor,
                        'scale' => 3,
                        'currency' => $commerce->currency,
                    ],
                ],
                '_artwork_id' => (int) $placement->id,
                '_media_id' => (int) $placement->media_id,
            ];
        }

        return $projected;
    }

    /** @param array<string, mixed> $slotMetadata
     * @return array{support:?string,support_color:?string}
     */
    private function displayProjection(array $slotMetadata): array
    {
        return [
            'support' => isset($slotMetadata['display_support']) ? (string) $slotMetadata['display_support'] : null,
            'support_color' => isset($slotMetadata['support_color']) ? (string) $slotMetadata['support_color'] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function findProjection(object $room, object $version, string $placementPublicId): array
    {
        foreach ($this->project($room, $version) as $projection) {
            if (hash_equals((string) $projection['id'], $placementPublicId)) {
                return $projection;
            }
        }

        throw new DomainException('The published VE-03 placement is unavailable.');
    }

    private function fits(object $slot, float $width, float $height): bool
    {
        $maximumWidth = (float) $slot->max_width_m;
        $maximumHeight = (float) $slot->max_height_m;

        return $width > 0
            && $height > 0
            && $width <= $maximumWidth
            && $height <= $maximumHeight;
    }

    /** @return array{style:string,color:?string,backing_depth_m:float} */
    private function frameProjection(mixed $frame): array
    {
        $frame = is_array($frame) ? $frame : [];
        $style = in_array(($frame['style'] ?? null), ['none', 'thin', 'wide'], true)
            ? (string) $frame['style']
            : 'none';
        $color = in_array(($frame['color'] ?? null), ['black', 'white', 'gold', 'silver', 'taupe', 'gray'], true)
            ? (string) $frame['color']
            : null;

        return [
            'style' => $style,
            'color' => $style === 'none' ? null : ($color ?? 'black'),
            'backing_depth_m' => $style === 'wide' ? 0.06 : 0.035,
        ];
    }

    /** @return list<float> */
    private function decodeVector(string $json, int $length): array
    {
        $values = json_decode($json, true);
        if (! is_array($values) || count($values) !== $length) {
            throw new DomainException('The VE-03 slot transform is invalid.');
        }

        return array_map('floatval', $values);
    }

    private function dimensionLabel(object $artwork): string
    {
        return trim((string) $artwork->width).' × '.trim((string) $artwork->height)
            .($artwork->depth ? ' × '.trim((string) $artwork->depth) : '').' '.trim((string) $artwork->dimension_unit);
    }

    private function editionLabel(object $artwork): ?string
    {
        if (! $artwork->edition_type) {
            return null;
        }
        $number = $artwork->edition_number && $artwork->edition_total
            ? ' '.$artwork->edition_number.'/'.$artwork->edition_total
            : '';

        return ucfirst(str_replace('_', ' ', (string) $artwork->edition_type)).$number;
    }

    private function authenticityLabel(?object $authenticity): ?string
    {
        if (! $authenticity || ! $authenticity->owns_artwork || ! $authenticity->may_display_and_sell) {
            return null;
        }

        return 'Ownership and display authorization documented by the platform.';
    }

    /** @param array{minor?:int, currency?:string, scale?:int}|null $price */
    private function priceLabel(?array $price): ?string
    {
        if (! isset($price['minor'], $price['currency'])) {
            return null;
        }

        $scale = max(0, (int) ($price['scale'] ?? 3));

        return number_format((int) $price['minor'] / (10 ** $scale), $scale).' '.strtoupper((string) $price['currency']);
    }

    /** @param list<mixed> $values */
    private function joinValues(array $values): ?string
    {
        $filtered = array_values(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== ''));

        return $filtered === [] ? null : implode(' · ', $filtered);
    }
}
