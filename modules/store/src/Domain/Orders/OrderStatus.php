<?php

namespace Modules\Store\Domain\Orders;

enum OrderStatus: string
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Ready = 'ready';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Pending',
            self::Confirmed => __('store::ui.admin_order_status_confirmed'),
            self::Processing => __('store::ui.admin_order_status_processing'),
            self::Ready => __('store::ui.admin_order_status_ready'),
            self::Shipped => __('store::ui.admin_order_status_shipped'),
            self::Delivered => __('store::ui.admin_order_status_delivered'),
            self::Cancelled => __('store::ui.admin_order_status_cancelled'),
            self::Returned => __('store::ui.admin_order_status_returned'),
        };
    }

    public function customerLabel(): string
    {
        return match ($this) {
            self::New => 'Order Pending',
            self::Confirmed => 'Order Confirmed',
            self::Processing => 'Preparing Your Order',
            self::Ready => 'Ready for Delivery',
            self::Shipped => 'Out for Delivery',
            self::Delivered => __('store::ui.admin_order_status_delivered'),
            self::Cancelled => 'Order Cancelled',
            self::Returned => 'Order Returned',
        };
    }

    public function customerMessage(): string
    {
        return match ($this) {
            self::New => 'We received your order and will review it shortly.',
            self::Confirmed => 'Your order details and delivery information were confirmed.',
            self::Processing => 'Your products are being prepared.',
            self::Ready => 'Your order is ready to leave for delivery.',
            self::Shipped => 'Your order is on its way.',
            self::Delivered => 'Your order was delivered successfully.',
            self::Cancelled => 'This order was cancelled and will not be delivered.',
            self::Returned => 'This order was recorded as returned.',
        };
    }

    public function accountLabel(): string
    {
        return match ($this) {
            self::New => 'Ordered',
            self::Confirmed => __('store::ui.admin_order_status_confirmed'),
            self::Processing => __('store::ui.admin_order_status_processing'),
            self::Ready => __('store::ui.admin_order_status_ready'),
            self::Shipped => __('store::ui.admin_order_status_shipped'),
            self::Delivered => __('store::ui.admin_order_status_delivered'),
            self::Cancelled => __('store::ui.admin_order_status_cancelled'),
            self::Returned => __('store::ui.admin_order_status_returned'),
        };
    }
}
