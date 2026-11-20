<?php

namespace Modules\Store\Domain\Catalog;

enum ProductVisibility: string
{
    case Public = 'public';
    case Hidden = 'hidden';
}
