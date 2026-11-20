<?php

namespace Modules\Exhibition\Contracts\Readiness;

interface ExhibitionReadiness
{
    public function status(): ExhibitionReadinessData;
}
