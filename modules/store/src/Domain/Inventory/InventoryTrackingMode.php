<?php

namespace Modules\Store\Domain\Inventory;

enum InventoryTrackingMode: string
{
    case Tracked = 'tracked';
    case Untracked = 'untracked';
}
