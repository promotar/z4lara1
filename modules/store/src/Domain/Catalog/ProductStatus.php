<?php

namespace Modules\Store\Domain\Catalog;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
