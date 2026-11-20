<?php

namespace Modules\Store\Domain\Catalog;

enum AttributeDataType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Select = 'select';
}
