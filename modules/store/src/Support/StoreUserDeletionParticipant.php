<?php

namespace Modules\Store\Support;

use App\Models\User;
use App\Platform\Core\Contracts\UserDeletionParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class StoreUserDeletionParticipant implements UserDeletionParticipant
{
    public function pluginSlug(): string
    {
        return 'store';
    }

    public function blockers(User $user): array
    {
        $checks = [
            ['store_orders', 'created_by', 'order'],
            ['store_product_reviews', 'user_id', 'product review'],
            ['store_coupon_usages', 'user_id', 'coupon usage record'],
            ['store_artwork_details', 'owner_user_id', 'owned artwork'],
            ['store_artwork_reviews', 'reviewer_user_id', 'artwork review record'],
            ['store_external_sellable_lifecycle_events', 'customer_user_id', 'external sale record'],
        ];

        $blockers = [];
        foreach ($checks as [$table, $column, $label]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $count = DB::table($table)->where($column, $user->getKey())->count();
            if ($count > 0) {
                $blockers[] = "has {$count} {$label}".($count === 1 ? '' : 's');
            }
        }

        return $blockers;
    }

    public function cleanup(User $user): void
    {
        if (Schema::hasTable('store_shipping_addresses')) {
            DB::table('store_shipping_addresses')->where('user_id', $user->getKey())->delete();
        }

        if (Schema::hasTable('store_customer_profiles')) {
            DB::table('store_customer_profiles')->where('user_id', $user->getKey())->delete();
        }
    }
}
