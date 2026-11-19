<?php

namespace App\Platform\Core\Seo;

interface RobotsProvider
{
    /**
     * @return array<int, string>
     */
    public function lines(): array;
}
