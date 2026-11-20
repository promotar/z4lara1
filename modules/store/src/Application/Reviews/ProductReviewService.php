<?php

namespace Modules\Store\Application\Reviews;

use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Store\Application\Customer\CustomerResourceNotFound;
use Modules\Store\Domain\Orders\OrderStatus;
use Modules\Store\Domain\Shared\PublicIdGenerator;

final readonly class ProductReviewService
{
    public function __construct(private PublicIdGenerator $ids) {}

    /**
     * Add review state to immutable order-item snapshots.
     *
     * @param  Collection<int, object>  $items
     * @return Collection<int, object>
     */
    public function decorateOrderItems(object $order, Collection $items): Collection
    {
        if (! Schema::hasTable('store_product_reviews')) {
            return $items->each(function (object $item): void {
                $item->review = null;
                $item->can_review = false;
            });
        }

        $reviews = DB::table('store_product_reviews')
            ->where('order_id', $order->id)
            ->where('user_id', $order->created_by)
            ->get()
            ->keyBy('order_item_id');
        $received = in_array((string) $order->status, [
            OrderStatus::Delivered->value,
            OrderStatus::Returned->value,
        ], true);

        return $items->each(function (object $item) use ($reviews, $received): void {
            $item->review = $reviews->get($item->id);
            $item->can_review = $received && $item->review === null;
        });
    }

    /** @param array{rating: int, title?: ?string, body: string} $data */
    public function create(int $userId, string $reviewerName, string $orderPublicId, string $itemPublicId, array $data): string
    {
        try {
            return DB::transaction(function () use ($userId, $reviewerName, $orderPublicId, $itemPublicId, $data): string {
                $item = DB::table('store_order_items as item')
                    ->join('store_orders as orders', 'orders.id', '=', 'item.order_id')
                    ->join('store_products as product', 'product.public_id', '=', 'item.product_public_id')
                    ->where('orders.public_id', $orderPublicId)
                    ->where('item.public_id', $itemPublicId)
                    ->where('orders.created_by', $userId)
                    ->lockForUpdate()
                    ->first([
                        'item.id as order_item_id',
                        'item.order_id',
                        'product.id as product_id',
                        'orders.status as order_status',
                    ]);

                if ($item === null) {
                    throw new CustomerResourceNotFound('Order item does not exist.');
                }
                if (! in_array((string) $item->order_status, [OrderStatus::Delivered->value, OrderStatus::Returned->value], true)) {
                    throw new InvalidArgumentException(__('store::ui.store_auto_7f28ecb86a'));
                }
                if (DB::table('store_product_reviews')->where('user_id', $userId)->where('order_item_id', $item->order_item_id)->exists()) {
                    throw new InvalidArgumentException(__('store::ui.store_auto_9c554cb22d'));
                }

                $publicId = (string) $this->ids->generate();
                DB::table('store_product_reviews')->insert([
                    'public_id' => $publicId,
                    'product_id' => $item->product_id,
                    'order_id' => $item->order_id,
                    'order_item_id' => $item->order_item_id,
                    'user_id' => $userId,
                    'reviewer_name' => trim($reviewerName) !== '' ? trim($reviewerName) : __('store::ui.store_auto_6c3727bd98'),
                    'rating' => (int) $data['rating'],
                    'title' => ($title = trim((string) ($data['title'] ?? ''))) === '' ? null : $title,
                    'body' => trim($data['body']),
                    'status' => 'published',
                    'verified_purchase' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $publicId;
            });
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'store_reviews_user_item_unique')) {
                throw new InvalidArgumentException(__('store::ui.store_auto_9c554cb22d'), previous: $exception);
            }

            throw $exception;
        }
    }

    /** @return array{average: float, count: int, items: Collection<int, object>} */
    public function forProduct(int $productId): array
    {
        if (! Schema::hasTable('store_product_reviews')) {
            return ['average' => 0.0, 'count' => 0, 'items' => collect()];
        }

        $query = DB::table('store_product_reviews')
            ->where('product_id', $productId)
            ->where('status', 'published');
        $summary = (clone $query)->selectRaw('COUNT(*) as aggregate, COALESCE(AVG(rating), 0) as average')->first();

        return [
            'average' => round((float) ($summary->average ?? 0), 1),
            'count' => (int) ($summary->aggregate ?? 0),
            'items' => $query->orderByDesc('created_at')->orderByDesc('id')->limit(30)->get(),
        ];
    }
}
