<?php

namespace Modules\Exhibition\Console\Commands;

use DomainException;
use Illuminate\Console\Command;
use Modules\Exhibition\Application\Inventory\ArtworkInventoryService;

final class ReleaseExpiredInventoryHoldsCommand extends Command
{
    protected $signature = 'exhibitions:inventory:release-expired {--limit=100 : Maximum expired holds to release}';

    protected $description = 'Release expired Exhibitions inventory holds and synchronize Store availability.';

    public function __construct(private readonly ArtworkInventoryService $inventory)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1 || $limit > 1000) {
            $this->error('The --limit option must be an integer between 1 and 1,000.');

            return self::FAILURE;
        }

        try {
            $released = $this->inventory->releaseExpired((int) $limit);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Released %d expired Exhibitions inventory hold(s).', $released));

        return self::SUCCESS;
    }
}
