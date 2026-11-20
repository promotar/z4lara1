<?php

namespace Modules\Store\Console\Commands;

use Illuminate\Console\Command;
use Modules\Store\Application\Orders\CodReservationExpiryService;

final class ExpireCodReservationsCommand extends Command
{
    protected $signature = 'store:orders:expire-cod {--limit=500}';

    protected $description = 'Cancel unconfirmed COD orders older than 24 hours and release their inventory reservations.';

    public function handle(CodReservationExpiryService $service): int
    {
        $count = $service->expire((int) $this->option('limit'));
        $this->info("Expired {$count} COD order(s).");

        return self::SUCCESS;
    }
}
