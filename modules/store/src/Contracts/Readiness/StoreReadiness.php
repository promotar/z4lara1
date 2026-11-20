<?php

namespace Modules\Store\Contracts\Readiness;

interface StoreReadiness
{
    public function status(): StoreReadinessData;
}
