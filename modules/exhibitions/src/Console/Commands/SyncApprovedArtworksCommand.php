<?php

namespace Modules\Exhibition\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Exhibition\Application\Publication\ArtworkPublicationService;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use Throwable;

final class SyncApprovedArtworksCommand extends Command
{
    protected $signature = 'exhibitions:artworks:sync-approved {--limit=100 : Maximum approved artworks to synchronize}';

    protected $description = 'Automatically publish approved Exhibitions artworks to Store and retry recoverable synchronization failures.';

    public function __construct(
        private readonly ArtworkPublicationService $publication,
        private readonly ExhibitionSettingsService $settings,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 1000) {
            $this->error('The --limit option must be an integer between 1 and 1,000.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('exhibition_artworks') || ! Schema::hasTable('exhibition_artwork_publications')) {
            $this->info('Exhibitions publication tables are not available; automatic synchronization was skipped safely.');

            return self::SUCCESS;
        }

        $storeContract = 'Modules\\Store\\Contracts\\Integrations\\V1\\ExternalSellableGateway';
        if (
            ! (bool) $this->settings->value('exhibitions.store.enabled', true)
            || ! interface_exists($storeContract)
            || ! app()->bound($storeContract)
        ) {
            $this->info('Store publication integration is inactive; approved artworks remain safely approved.');

            return self::SUCCESS;
        }

        $rows = DB::table('exhibition_artworks as artwork')
            ->leftJoin('exhibition_artwork_publications as publication', 'publication.artwork_id', '=', 'artwork.id')
            ->where('artwork.state', 'approved')
            ->whereNotNull('artwork.approved_by')
            ->where(function ($query): void {
                $query->whereNull('publication.id')
                    ->orWhere(function ($failed): void {
                        $failed->whereIn('publication.state', ['pending', 'sync_failed'])
                            ->where(function ($due): void {
                                $due->whereNull('publication.last_attempt_at')
                                    ->orWhere('publication.last_attempt_at', '<=', now()->subMinutes(5));
                            });
                    });
            })
            ->orderBy('artwork.approved_at')
            ->limit((int) $limit)
            ->get([
                'artwork.public_id',
                'artwork.approved_by',
                'publication.attempt_count',
            ]);

        $published = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $attempt = ((int) ($row->attempt_count ?? 0)) + 1;
                $result = $this->publication->publish(
                    (int) $row->approved_by,
                    (string) $row->public_id,
                    'Automatic Store synchronization for a finally approved artwork.',
                    'automatic-approved-sync:'.$row->public_id.':'.$attempt,
                );

                if ($result->state === 'published') {
                    $published++;
                } else {
                    $failed++;
                }
            } catch (Throwable $exception) {
                $failed++;
                report($exception);
            }
        }

        $this->info(sprintf('Approved artwork synchronization completed: %d published, %d awaiting retry.', $published, $failed));

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
