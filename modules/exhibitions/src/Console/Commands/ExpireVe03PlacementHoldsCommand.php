<?php

namespace Modules\Exhibition\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ExpireVe03PlacementHoldsCommand extends Command
{
    protected $signature = 'exhibitions:ve03:expire-placement-holds {--limit=500}';
    protected $description = 'Remove expired VE-03 artist display holds without changing placements or users.';

    public function handle(): int
    {
        if (! Schema::hasTable('exhibition_virtual_slot_holds')) return self::SUCCESS;
        $ids = DB::table('exhibition_virtual_slot_holds')->where('expires_at', '<=', now())->orderBy('id')->limit(max(1, min(5000, (int) $this->option('limit'))))->pluck('id');
        $deleted = $ids->isEmpty() ? 0 : DB::table('exhibition_virtual_slot_holds')->whereIn('id', $ids)->delete();
        $this->info("Expired VE-03 holds removed: {$deleted}");

        return self::SUCCESS;
    }
}
