<?php

namespace Modules\Seo\Console;

use Illuminate\Console\Command;
use Modules\Seo\Services\SeoModuleSyncManager;

final class PurgeModule extends Command
{
    protected $signature = 'seo:purge {slug : Module slug} {--confirm= : Repeat the slug to confirm destructive deletion}';

    protected $description = 'Permanently delete SEO settings, meta and redirects for a module.';

    public function handle(SeoModuleSyncManager $sync): int
    {
        $slug = trim((string) $this->argument('slug'));
        $confirm = trim((string) $this->option('confirm'));

        if ($slug === '' || $confirm !== $slug) {
            $this->error('Refusing to purge without --confirm='.$slug);

            return self::FAILURE;
        }

        if (! $this->confirm("Permanently delete all SEO data for [{$slug}]?")) {
            $this->warn('Purge cancelled.');

            return self::SUCCESS;
        }

        $sync->purge($slug);
        $this->info("SEO data for [{$slug}] was permanently deleted.");

        return self::SUCCESS;
    }
}
