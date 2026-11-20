<?php

namespace Modules\Store\Domain\Settings;

use DateTimeImmutable;
use Modules\Store\Domain\Shared\ActorContext;

interface StoreSettingsRepository
{
    /** @return array<string, mixed> */
    public function all(): array;

    /** @param array<string, mixed> $settings */
    public function put(array $settings, ActorContext $actor, DateTimeImmutable $now): void;
}
