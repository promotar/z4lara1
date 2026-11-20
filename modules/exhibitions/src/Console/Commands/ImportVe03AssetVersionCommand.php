<?php

namespace Modules\Exhibition\Console\Commands;

use Illuminate\Console\Command;
use Modules\Exhibition\Application\VirtualExhibitions\Ve03AssetVersionImporter;

final class ImportVe03AssetVersionCommand extends Command
{
    protected $signature = 'exhibitions:ve03:asset-import
        {source : Absolute or project-relative quarantined source GLB}
        {build : Absolute or project-relative Phase 2 build directory}
        {--actor=1 : User ID recorded as pipeline actor}
        {--publish : Publish internally as the active VE-03 asset version}';

    protected $description = 'Import a validated VE-03 Phase 2 build into immutable local storage and version metadata.';

    public function handle(Ve03AssetVersionImporter $importer): int
    {
        $source = $this->absolute((string) $this->argument('source'));
        $build = $this->absolute((string) $this->argument('build'));
        $version = $importer->import($source, $build, (int) $this->option('actor'), (bool) $this->option('publish'));

        $this->info(sprintf(
            'VE-03 environment version %d is %s (runtime %s).',
            $version['version_number'],
            $version['status'],
            $version['runtime_sha256'],
        ));

        return self::SUCCESS;
    }

    private function absolute(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }
}
