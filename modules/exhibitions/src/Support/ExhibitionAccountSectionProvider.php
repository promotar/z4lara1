<?php

namespace Modules\Exhibition\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ExhibitionAccountSectionProvider
{
    /** @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    public function append(array $sections, User $user): array
    {
        if (! Schema::hasTable('exhibition_artist_identities')) {
            return $sections;
        }

        $sections = $this->addExhibitionShortcutsToCustomerArea($sections);

        $identity = DB::table('exhibition_artist_identities')
            ->where('core_user_id', $user->getKey())
            ->first(['id', 'state']);
        $application = Schema::hasTable('exhibition_artist_applications')
            ? DB::table('exhibition_artist_applications')->where('core_user_id', $user->getKey())->first(['state'])
            : null;

        if ($identity === null && $application === null) {
            return $sections;
        }

        $artworkCount = $identity !== null && Schema::hasTable('exhibition_artworks')
            ? DB::table('exhibition_artworks')->where('artist_identity_id', $identity->id)->count()
            : 0;

        $sections[] = [
            'key' => 'artist',
            'label' => __('exhibitions::ui.account_artist_studio'),
            'icon' => 'E',
            'order' => 40,
            'badge' => (string) $artworkCount,
            'view' => 'exhibitions::account.summary',
            'data' => [
                'identityState' => $identity?->state,
                'applicationState' => $application?->state,
                'artworkCount' => $artworkCount,
                'studioUrl' => route($identity !== null ? 'exhibitions.dashboard.overview' : 'exhibitions.application.edit'),
            ],
        ];

        return $sections;
    }

    /**
     * @param list<array<string, mixed>> $sections
     * @return list<array<string, mixed>>
     */
    private function addExhibitionShortcutsToCustomerArea(array $sections): array
    {
        foreach ($sections as $index => $section) {
            if (($section['key'] ?? null) !== 'store') {
                continue;
            }

            $sections[$index]['view'] = 'exhibitions::account.customer-area';
            $sections[$index]['data'] = [
                'customerAreaView' => $section['view'],
                'customerAreaData' => is_array($section['data'] ?? null) ? $section['data'] : [],
                'artworksUrl' => route('exhibitions.artworks.index'),
                'virtualExhibitionUrl' => route('exhibitions.virtual.index'),
            ];

            break;
        }

        return $sections;
    }
}
