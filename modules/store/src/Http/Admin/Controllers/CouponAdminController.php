<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Modules\Store\Application\Catalog\CatalogTranslationSelector;

final class CouponAdminController extends Controller
{
    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly CatalogTranslationSelector $translations,
    ) {}

    public function index(Request $request): View
    {
        $coupons = $this->database->table('store_promotions as coupon')
            ->join('store_promotion_actions as action', 'action.promotion_id', '=', 'coupon.id')
            ->where('coupon.mode', 'code')
            ->orderByDesc('coupon.created_at')
            ->paginate(25, [
                'coupon.public_id',
                'coupon.name',
                'coupon.code_normalized as code',
                'coupon.status',
                'coupon.starts_at',
                'coupon.ends_at',
                'coupon.usage_limit',
                'coupon.per_customer_limit',
                'coupon.version',
                'action.action_type',
                'action.amount_minor',
                'action.percentage_basis_points',
                $this->database->raw("(SELECT COUNT(*) FROM store_coupon_usages usage_row WHERE usage_row.promotion_id = coupon.id AND usage_row.status = 'counted') as usage_count"),
            ])->withQueryString();

        return view('store::admin.coupons.index', ['coupons' => $coupons]);
    }

    public function create(): View
    {
        return view('store::admin.coupons.form', $this->catalog() + ['coupon' => null, 'targets' => collect()]);
    }

    public function edit(string $coupon): View
    {
        $record = $this->database->table('store_promotions as coupon')
            ->join('store_promotion_actions as action', 'action.promotion_id', '=', 'coupon.id')
            ->where('coupon.public_id', $coupon)
            ->where('coupon.mode', 'code')
            ->first([
                'coupon.*',
                'action.action_type',
                'action.amount_minor',
                'action.percentage_basis_points',
            ]);
        abort_if($record === null, 404);
        $targets = $this->database->table('store_promotion_targets')
            ->where('promotion_id', $record->id)
            ->get(['target_type', 'target_public_id']);

        return view('store::admin.coupons.form', $this->catalog() + [
            'coupon' => $record,
            'targets' => $targets,
        ]);
    }

    /** @return array{products: Collection, categories: Collection} */
    private function catalog(): array
    {
        $locale = (string) app()->getLocale();
        $products = $this->database->table('store_products as product')
            ->leftJoinSub($this->translations->products($locale), 'product_translation_choice', fn ($join) => $join->on('product_translation_choice.product_id', '=', 'product.id'))
            ->leftJoin('store_product_translations as translation', 'translation.id', '=', 'product_translation_choice.translation_id')
            ->where('product.status', '!=', 'archived')
            ->orderBy('translation.name')
            ->get(['product.public_id', 'translation.name']);
        $categories = $this->database->table('store_categories as category')
            ->leftJoinSub($this->translations->categories($locale), 'category_translation_choice', fn ($join) => $join->on('category_translation_choice.category_id', '=', 'category.id'))
            ->leftJoin('store_category_translations as translation', 'translation.id', '=', 'category_translation_choice.translation_id')
            ->where('category.status', 'active')
            ->orderBy('translation.name')
            ->get(['category.public_id', 'translation.name']);

        return ['products' => $products, 'categories' => $categories];
    }
}
