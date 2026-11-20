<?php

namespace Modules\Store\Domain\Shared;

use InvalidArgumentException;

final readonly class ActorContext
{
    public function __construct(
        public string $type,
        public ?string $id = null,
        public ?string $name = null,
    ) {
        if (! in_array($type, ['user', 'system', 'integration'], true)) {
            throw new InvalidArgumentException('Unsupported Store actor type.');
        }

        if ($type === 'user' && ($id === null || trim($id) === '')) {
            throw new InvalidArgumentException('A user actor requires an identifier.');
        }
    }

    public static function system(?string $id = null): self
    {
        return new self('system', $id);
    }
}
