<?php

namespace Modules\Exhibition\Events\V1;

final readonly class ExhibitionRuntimeRegistered
{
    public function __construct(public string $version) {}
}
