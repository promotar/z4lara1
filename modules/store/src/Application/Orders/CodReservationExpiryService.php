<?php

namespace Modules\Store\Application\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Modules\Store\Application\Inventory\InventoryService;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Infrastructure\Integrations\DatabaseExternalSellableLifecycleFeed;

final readonly class CodReservationExpiryService
{
    public function __construct(
        private InventoryService $inventory,
        private OrderEventRecorder $events,
        private DatabaseExternalSellableLifecycleFeed $externalLifecycle,
    ) {}

    public function expire(int $limit = 500): int
    {
        $cutoff = now()->subHours(24);
        $ids = DB::table('store_orders')
            ->where('status', 'new')
            ->where('payment_method', 'cash_on_delivery')
            ->where('placed_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(max(1, min(5000, $limit)))
            ->pluck('id');
        $expired = 0;

        foreach ($ids as $id) {
            $didExpire = DB::transaction(function () use ($id, $cutoff): bool {
                $order = DB::table('store_orders')->where('id', $id)->lockForUpdate()->first();
                if ($order === null || $order->status !== 'new' || $order->payment_method !== 'cash_on_delivery' || Carbon::parse($order->placed_at)->isAfter($cutoff)) {
                    return false;
                }

                $reservations = DB::table('store_order_items as item')
                    ->join('store_inventory_reservations as reservation', 'reservation.public_id', '=', 'item.inventory_reservation_public_id')
                    ->where('item.order_id', $order->id)
                    ->where('reservation.status', 'active')
                    ->pluck('reservation.public_id');
                foreach ($reservations as $reservation) {
                    $this->inventory->release((string) $reservation);
                }

                $updated = DB::table('store_orders')->where('id', $order->id)->where('status', 'new')->update([
                    'status' => 'cancelled',
                    'payment_status' => 'cancelled',
                    'cancellation_reason_code' => 'cod_confirmation_timeout',
                    'cancellation_reason' => 'COD order was not confirmed within 24 hours.',
                    'cancelled_at' => now(),
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);
                if ($updated !== 1) {
                    return false;
                }

                $this->events->record(
                    (int) $order->id,
                    'status_changed',
                    ActorContext::system('cod-reservation-expiry'),
                    'COD order expired after 24 hours without confirmation; inventory was released.',
                    'new',
                    'cancelled',
                    ['cancellation_reason_code' => 'cod_confirmation_timeout'],
                );
                $this->externalLifecycle->recordOrder((int) $order->id, 'released', 'cod-timeout');

                return true;
            }, 3);
            $expired += $didExpire ? 1 : 0;
        }

        return $expired;
    }
}
