<?php

namespace Modules\Exhibition\Console\Commands;

use DomainException;
use Illuminate\Console\Command;
use Modules\Exhibition\Application\Notifications\ExhibitionNotificationService;

final class ProcessDueNotificationsCommand extends Command
{
    protected $signature = 'exhibitions:notifications:process-due {--limit=100 : Maximum due deliveries to process}';

    protected $description = 'Process a bounded batch of due Exhibitions email deliveries.';

    public function __construct(private readonly ExhibitionNotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);

        if ($limit === false) {
            $this->error('The --limit option must be an integer.');

            return self::FAILURE;
        }

        try {
            $result = $this->notifications->processDue((int) $limit);
        } catch (DomainException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Processed %d: %d delivered, %d retry scheduled, %d permanently failed.',
            $result['processed'],
            $result['delivered'],
            $result['retry_scheduled'],
            $result['failed'],
        ));

        return self::SUCCESS;
    }
}
