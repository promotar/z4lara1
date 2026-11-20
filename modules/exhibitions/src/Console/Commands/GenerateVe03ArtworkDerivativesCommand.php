<?php

namespace Modules\Exhibition\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Exhibition\Application\Media\Ve03ArtworkDerivativeService;

final class GenerateVe03ArtworkDerivativesCommand extends Command
{
    protected $signature = 'exhibitions:ve03:artwork-derivatives {--force} {--limit=500}';

    protected $description = 'Generate approved sRGB artwork derivatives for the VE-03 viewer.';

    public function handle(Ve03ArtworkDerivativeService $derivatives): int
    {
        $query = DB::table('exhibition_artwork_media')
            ->where('state', 'ready')
            ->whereNotNull('display_path')
            ->orderBy('id')
            ->limit(max(1, min(5000, (int) $this->option('limit'))));
        if (! $this->option('force')) {
            $query->whereNull('ve03_derivatives_ready_at');
        }

        $generated = 0;
        $failed = 0;
        foreach ($query->get() as $media) {
            try {
                $derivatives->generateForMedia($media);
                $generated++;
            } catch (\Throwable $exception) {
                $failed++;
                $this->error("Media {$media->public_id}: {$exception->getMessage()}");
            }
        }

        $this->info("VE-03 derivatives generated: {$generated}; failed: {$failed}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
