<?php

namespace Modules\Store\Infrastructure\Clock;

use DateTimeImmutable;
use DateTimeZone;
use Modules\Store\Domain\Shared\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
