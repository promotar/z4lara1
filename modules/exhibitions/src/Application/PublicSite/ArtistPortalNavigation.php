<?php

namespace Modules\Exhibition\Application\PublicSite;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class ArtistPortalNavigation
{
    /** @return array{items:list<array<string,mixed>>}|null */
    public function forUser(int $userId): ?array
    {
        if (! Schema::hasTable('exhibition_artist_applications') || ! Schema::hasTable('exhibition_artist_identities')) {
            return null;
        }

        $application = DB::table('exhibition_artist_applications')->where('core_user_id', $userId)->first();
        if ($application === null) {
            return null;
        }
        $identityState = DB::table('exhibition_artist_identities')->where('core_user_id', $userId)->value('state');
        $applicationState = (string) $application->state;
        $needsEditing = in_array($applicationState, ['draft', 'documents_required', 'rejected'], true);
        $workspace = in_array($applicationState, ['approved', 'contract_pending', 'active'], true)
            && in_array($identityState, ['candidate', 'active', 'restricted'], true);
        $item = static fn (string $label, ?string $route, string $icon, bool $visible = true, array $children = [], array $params = []): array => [
            'label' => $label,
            'href' => $route !== null && Route::has($route) ? route($route, $params) : null,
            'active' => $route !== null && request()->routeIs($route),
            'icon' => $icon,
            'visible' => $visible,
            'type' => $children === [] ? 'link' : 'group',
            'children' => $children,
        ];

        $virtualChildren = [
            $item(__('exhibitions::ui.artist_nav_place'), 'exhibitions.dashboard.virtual.index', '+', true, [], ['tab' => 'available']),
            $item(__('exhibitions::ui.artist_nav_published'), 'exhibitions.dashboard.virtual.index', 'P', true, [], ['tab' => 'published']),
            $item(__('exhibitions::ui.artist_nav_removed'), 'exhibitions.dashboard.virtual.index', 'R', true, [], ['tab' => 'removed']),
        ];

        return [
            'items' => [
                $item(__('exhibitions::ui.artist_nav_overview'), 'exhibitions.dashboard.overview', 'D', $workspace),
                $item(__('exhibitions::ui.artist_nav_application'), $needsEditing ? 'exhibitions.application' : 'exhibitions.application.status', 'A', ! $workspace),
                $item(__('exhibitions::ui.artist_nav_artworks'), 'exhibitions.dashboard.artworks', 'W', $workspace),
                $item(__('exhibitions::ui.artist_nav_virtual'), null, 'V', $workspace, $virtualChildren),
                $item(__('exhibitions::ui.artist_nav_sold'), 'exhibitions.dashboard.sales', 'S', $workspace),
                $item(__('exhibitions::ui.artist_nav_earnings'), 'exhibitions.dashboard.earnings', 'E', $workspace),
                $item(__('exhibitions::ui.artist_nav_profile'), 'exhibitions.dashboard.profile', 'R', $workspace),
                $item(__('exhibitions::ui.artist_nav_support'), 'exhibitions.dashboard.support', 'H', $workspace),
            ],
        ];
    }
}
