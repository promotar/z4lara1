<?php

namespace Modules\Exhibition\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03RuntimeManifestService;

final readonly class VirtualExhibitionsPageBuilderExtension
{
    private const SLOT_PATTERN = '/<div\b(?=[^>]*\bdata-exhibitions-virtual-exhibitions\s*=)[^>]*>[\s\S]*?<\/div>/i';

    public function __construct(private Ve03RuntimeManifestService $runtime) {}

    /** @param array<int, array<string, mixed>> $extensions */
    public function editorExtensions(array $extensions, object $page): array
    {
        if (! $this->isReady()) {
            return $extensions;
        }

        $rooms = $this->rooms('newest', 24);
        $extensions[] = [
            'id' => 'exhibitions.virtual-exhibitions',
            'config' => [
                'previewHtml' => $this->cardsHtml($rooms),
                'hasRooms' => $rooms->isNotEmpty(),
            ],
            'scripts' => [url('/platform/plugins/exhibitions/admin/js/vvveb-virtual-exhibitions.js?v=20260820-2')],
            'styles' => [],
        ];

        return $extensions;
    }

    public function renderFrontendHtml(string $html, object $context): string
    {
        if (! $this->isReady() || ! str_contains($html, 'data-exhibitions-virtual-exhibitions')) {
            return $html;
        }

        return preg_replace_callback(self::SLOT_PATTERN, function (array $match): string {
            $attributes = $this->attributes($match[0]);
            $limit = max(1, min(24, (int) ($attributes['data-exhibitions-virtual-exhibitions-limit'] ?? 6)));
            $order = (string) ($attributes['data-exhibitions-virtual-exhibitions-order'] ?? 'newest');
            $instance = $this->instance($attributes);
            $rooms = $this->rooms($order, $limit);

            if ($rooms->isEmpty()) {
                return '';
            }

            return '<section class="exhibitions-virtual-exhibitions" data-exhibitions-virtual-exhibitions-rendered="'.e($instance).'">'
                .$this->styles($instance, $attributes)
                .'<div class="exhibitions-virtual-exhibitions-items">'.$this->cardsHtml($rooms).'</div>'
                .'</section>';
        }, $html) ?? $html;
    }

    /** @return Collection<int, object> */
    private function rooms(string $order, int $limit): Collection
    {
        $legacy = Schema::hasTable('exhibition_virtual_rooms')
            ? DB::table('exhibition_virtual_rooms as room')
                ->where('room.status', 'published')
                ->select(['room.id', 'room.public_id', 'room.title', 'room.slug', 'room.short_description', 'room.published_at'])
                ->selectSub(fn ($query) => $query->from('exhibition_virtual_placement_requests as placement')->whereColumn('placement.room_id', 'room.id')->where('placement.status', 'published')->selectRaw('count(*)'), 'artworks_count')
                ->selectSub(fn ($query) => $query->from('exhibition_virtual_room_slots as slot')->whereColumn('slot.room_id', 'room.id')->where('slot.enabled', true)->selectRaw('count(*)'), 'displays_count')
                ->get()
            : collect();
        $rooms = $this->runtime->publishedRooms()->concat($legacy)->values();

        $rooms = match ($order) {
            'oldest' => $rooms->sortBy('published_at'),
            'title' => $rooms->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE),
            'random' => $rooms->shuffle(),
            default => $rooms->sortByDesc('published_at'),
        };

        return $rooms->take($limit)->values();
    }

    /** @param Collection<int, object> $rooms */
    private function cardsHtml(Collection $rooms): string
    {
        return $rooms->map(function (object $room): string {
            $url = route('exhibitions.virtual.show', ['room' => $room->slug], false);

            return '<article class="exhibitions-virtual-exhibition-card">'
                .'<a class="exhibitions-virtual-exhibition-visual" href="'.e($url).'" aria-label="'.e((string) $room->title).'"><span>3D</span><b>Virtual Tour</b></a>'
                .'<div class="exhibitions-virtual-exhibition-body"><h3><a href="'.e($url).'">'.e((string) $room->title).'</a></h3>'
                .'<p class="exhibitions-virtual-exhibition-description">'.e((string) ($room->short_description ?: 'Explore artworks inside an interactive virtual exhibition.')).'</p>'
                .'<p class="exhibitions-virtual-exhibition-stats"><span>'.e((string) ($room->artworks_count ?? 0)).' artworks</span><span>'.e((string) ($room->displays_count ?? 0)).' displays</span></p>'
                .'<a class="exhibitions-virtual-exhibition-action" href="'.e($url).'">Enter exhibition</a></div>'
                .'</article>';
        })->implode('');
    }

    private function styles(string $instance, array $attributes): string
    {
        $selector = '[data-exhibitions-virtual-exhibitions-rendered="'.e($instance).'"]';
        $desktop = max(1, min(6, (int) ($attributes['data-exhibitions-virtual-exhibitions-columns-desktop'] ?? 3)));
        $tablet = max(1, min(4, (int) ($attributes['data-exhibitions-virtual-exhibitions-columns-tablet'] ?? 2)));
        $mobile = max(1, min(2, (int) ($attributes['data-exhibitions-virtual-exhibitions-columns-mobile'] ?? 1)));
        $gap = max(0, min(120, (int) ($attributes['data-exhibitions-virtual-exhibitions-gap'] ?? 24)));
        $showDescription = ($attributes['data-exhibitions-virtual-exhibitions-show-description'] ?? '1') !== '0';
        $showStats = ($attributes['data-exhibitions-virtual-exhibitions-show-stats'] ?? '1') !== '0';

        return '<style data-exhibitions-virtual-exhibitions-styles>'
            .$selector.' .exhibitions-virtual-exhibitions-items{display:grid;grid-template-columns:repeat('.$desktop.',minmax(0,1fr));gap:'.$gap.'px}'
            .$selector.' .exhibitions-virtual-exhibition-card{overflow:hidden;border:1px solid rgba(15,23,42,.14);border-radius:16px;background:#111827;color:#fff}'
            .$selector.' .exhibitions-virtual-exhibition-visual{display:grid;min-height:180px;place-content:center;text-align:center;background:radial-gradient(circle at 50% 20%,#374151,#111827 65%);color:#fff;text-decoration:none}'.$selector.' .exhibitions-virtual-exhibition-visual span{font:900 3rem/1 system-ui,sans-serif}'.$selector.' .exhibitions-virtual-exhibition-visual b{margin-top:8px;font:700 12px/1.4 system-ui,sans-serif;text-transform:uppercase;letter-spacing:.15em}'
            .$selector.' .exhibitions-virtual-exhibition-body{display:grid;gap:12px;padding:20px}'.$selector.' h3,'.$selector.' p{margin:0}'.$selector.' h3{font:700 1.1rem/1.4 system-ui,sans-serif}'.$selector.' h3 a{color:inherit;text-decoration:none}'
            .$selector.' .exhibitions-virtual-exhibition-description{color:#cbd5e1;font:400 14px/1.6 system-ui,sans-serif}'.($showDescription ? '' : $selector.' .exhibitions-virtual-exhibition-description{display:none}')
            .$selector.' .exhibitions-virtual-exhibition-stats{display:flex;gap:12px;color:#fca5a5;font:700 12px/1.4 system-ui,sans-serif}'.($showStats ? '' : $selector.' .exhibitions-virtual-exhibition-stats{display:none}')
            .$selector.' .exhibitions-virtual-exhibition-action{color:#fff;font:700 13px/1.4 system-ui,sans-serif}'
            .'@media(max-width:991px){'.$selector.' .exhibitions-virtual-exhibitions-items{grid-template-columns:repeat('.$tablet.',minmax(0,1fr))}}@media(max-width:575px){'.$selector.' .exhibitions-virtual-exhibitions-items{grid-template-columns:repeat('.$mobile.',minmax(0,1fr))}}'
            .'</style>';
    }

    /** @return array<string, string> */
    private function attributes(string $slot): array
    {
        preg_match('/^<div\b([^>]*)>/i', $slot, $opening);
        preg_match_all('/\b(data-exhibitions-virtual-exhibitions[a-z0-9-]*)\s*=\s*(["\'])(.*?)\2/is', (string) ($opening[1] ?? ''), $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $match): array => [strtolower($match[1]) => html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8')])->all();
    }

    private function instance(array $attributes): string
    {
        $value = strtolower((string) ($attributes['data-exhibitions-virtual-exhibitions-instance'] ?? ''));
        $value = trim((string) preg_replace('/[^a-z0-9_-]+/', '-', $value), '-');

        return $value !== '' ? substr($value, 0, 80) : 'virtual-exhibitions-'.substr(sha1((string) json_encode($attributes)), 0, 12);
    }

    private function isReady(): bool
    {
        return Route::has('exhibitions.virtual.show')
            && (Schema::hasTable('exhibition_virtual_rooms') || Schema::hasTable('exhibition_virtual_environments'));
    }
}
