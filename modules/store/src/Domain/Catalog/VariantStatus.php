<?php

namespace Modules\Store\Domain\Catalog;

enum VariantStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';
}
