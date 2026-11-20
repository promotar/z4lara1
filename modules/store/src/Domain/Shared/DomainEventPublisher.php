<?php

namespace Modules\Store\Domain\Shared;

interface DomainEventPublisher
{
    public function afterCommit(object $event): void;
}
