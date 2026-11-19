<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Restore compiled Vite assets (public/build/assets) from the persistent
 * platform storage. This keeps the frontend/admin styling working after a
 * deployment, because build artifacts are intentionally not part of the
 * core source archive (.gitignore) and the container image only ships
 * public/build/manifest.json.
 *
 * Usage (post-deployment):
 *   php artisan platform:restore-build-assets
 *   php artisan platform:restore-build-assets --force
 */
class RestoreBuildAssets extends Command
{
    protected $signature = 'platform:restore-build-assets
                            {--force : Overwrite files that already exist}';

    protected $description = 'Restore public/build/assets from storage/app/platform/build-assets after a deployment.';

    public function handle(): int
    {
        $source = storage_path('app/platform/build-assets');
        $target = public_path('build/assets');

        if (! File::isDirectory($source)) {
            $this->error("Build asset backup not found: {$source}");

            return self::FAILURE;
        }

        $files = File::files($source);

        if ($files === []) {
            $this->error("No build assets found in: {$source}");

            return self::FAILURE;
        }

        File::ensureDirectoryExists($target, 0775, true);

        $copied = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $destination = $target.DIRECTORY_SEPARATOR.$file->getFilename();

            if (! $this->option('force') && File::exists($destination)) {
                $skipped++;

                continue;
            }

            File::copy($file->getPathname(), $destination);
            @chmod($destination, 0664);
            $copied++;
        }

        $this->info(sprintf(
            'Build assets restored: %d copied, %d skipped (source: %s).',
            $copied,
            $skipped,
            $source
        ));

        if (! File::exists(public_path('build/manifest.json'))) {
            $this->warn('public/build/manifest.json is missing; the Vite manifest is required for asset URLs.');
        }

        return self::SUCCESS;
    }
}
