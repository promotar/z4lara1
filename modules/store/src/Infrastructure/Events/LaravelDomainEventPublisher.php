<?php

namespace Modules\Store\Infrastructure\Events;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Store\Domain\Shared\DomainEventPublisher;

final class LaravelDomainEventPublisher implements DomainEventPublisher
{
    public function afterCommit(object $event): void
    {
        DB::afterCommit(static fn (): mixed => Event::dispatch($event));
    }
}
