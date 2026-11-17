<?php

namespace App\Platform\Core\Hooks;

class HookCallback
{
    public function __construct(
        public readonly string $id,
        public readonly mixed $callback,
        public readonly int $priority = 10,
        public readonly int $acceptedArgs = 1,
    ) {
        //
    }
}
