<?php

namespace Modules\Exhibition\Contracts\Payments\V1;

interface PayoutOutcomeReceiver
{
    public function receive(PayoutOutcome $outcome): object;
}
