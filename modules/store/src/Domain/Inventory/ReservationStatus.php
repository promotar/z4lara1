<?php

namespace Modules\Store\Domain\Inventory;

enum ReservationStatus: string
{
    case Active = 'active';
    case Committed = 'committed';
    case Released = 'released';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
