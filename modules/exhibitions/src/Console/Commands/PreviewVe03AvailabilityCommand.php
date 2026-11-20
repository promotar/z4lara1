<?php

namespace Modules\Exhibition\Console\Commands;

use Illuminate\Console\Command;
use Modules\Exhibition\Contracts\Store\V1\ArtworkPublicationGateway;

final class PreviewVe03AvailabilityCommand extends Command
{
    protected $signature = 'exhibitions:ve03:preview-availability
        {artwork : Artwork public ID}
        {state : available or unavailable}
        {--quantity=1 : Available quantity when state is available}';

    protected $description = 'Temporarily exercise the authoritative Store-to-VE-03 availability bridge in local/testing.';

    public function handle(ArtworkPublicationGateway $store): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This verification command is limited to local/testing environments.');

            return self::FAILURE;
        }
        $state = strtolower((string) $this->argument('state'));
        if (! in_array($state, ['available', 'unavailable'], true)) {
            $this->error('State must be available or unavailable.');

            return self::INVALID;
        }
        $quantity = $state === 'available' ? max(1, (int) $this->option('quantity')) : 0;
        $result = $store->syncAvailability(
            (string) $this->argument('artwork'),
            $quantity,
            'VE-03 local availability preview.',
            've03-local-preview:'.now()->format('YmdHisv').':'.$state,
        );
        if (! $result->succeeded) {
            $this->error($result->message ?: 'Store availability update failed.');

            return self::FAILURE;
        }
        $this->info("VE-03 availability changed to {$state} (quantity {$quantity}).");

        return self::SUCCESS;
    }
}
