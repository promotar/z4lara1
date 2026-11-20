<?php

namespace Modules\Seo\Console;

use Illuminate\Console\Command;
use Modules\Seo\Models\SeoModuleState;
use Modules\Seo\Services\SeoModuleSyncManager;
use Modules\Seo\Services\SeoSiteResolver;

final class SyncModules extends Command
{
    protected $signature = 'seo:sync-modules {--purge : Purge expired archived modules} {--dry-run : Report without changing state}';

    protected $description = 'Reconcile SEO module states with the active plugin registry.';

    public function handle(SeoModuleSyncManager $sync, SeoSiteResolver $sites): int
    {
        if ($this->option('dry-run')) {
            $states = SeoModuleState::query()
                ->where('site_id', $sites->id())
                ->orderBy('module_slug')
                ->get(['module_slug', 'status', 'purge_after']);

            $this->table(
                ['module', 'status', 'purge_after'],
                $states->map(static fn (SeoModuleState $state): array => [
                    $state->module_slug,
                    $state->status,
                    $state->purge_after?->toDateTimeString() ?? '—',
                ])->all(),
            );

            return self::SUCCESS;
        }

        $result = $sync->reconcile((bool) $this->option('purge'));

        $this->info('SEO module reconciliation completed.');
        $this->line('orphaned: '.implode(', ', $result['orphaned'] ?: ['—']));
        $this->line('archived: '.implode(', ', $result['archived'] ?: ['—']));
        $this->line('purged: '.implode(', ', $result['purged'] ?: ['—']));

        return self::SUCCESS;
    }
}
