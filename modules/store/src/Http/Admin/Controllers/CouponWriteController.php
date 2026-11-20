<?php

namespace Modules\Store\Http\Admin\Controllers;

use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Application\Coupons\CouponDefinition;
use Modules\Store\Application\Coupons\CouponManagementService;
use Modules\Store\Domain\Promotions\PercentageBasisPoints;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class CouponWriteController extends Controller
{
    use HandlesStoreAdminRequests;

    public function store(Request $request, CouponManagementService $coupons): RedirectResponse
    {
        try {
            $publicId = $coupons->create($this->definition($request));

            return redirect()->route('admin.plugins.store.coupons.edit', $publicId)
                ->with('success', 'Coupon created successfully.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function update(Request $request, string $coupon, CouponManagementService $coupons): RedirectResponse
    {
        $version = $request->validate(['version' => ['required', 'integer', 'min:1']])['version'];
        try {
            $coupons->update($coupon, (int) $version, $this->definition($request));

            return back()->with('success', 'Coupon updated successfully. Existing orders were not changed.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function status(Request $request, string $coupon, CouponManagementService $coupons): RedirectResponse
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'active' => ['required', 'boolean'],
        ]);
        try {
            $coupons->setActive($coupon, (int) $data['version'], (bool) $data['active'], (int) $request->user()->id);

            return back()->with('success', (bool) $data['active'] ? 'Coupon activated.' : 'Coupon disabled.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function destroy(Request $request, string $coupon, CouponManagementService $coupons): RedirectResponse
    {
        $data = $request->validate([
            'coupon_code' => ['required', 'string', 'max:64'],
        ]);

        try {
            $coupons->delete($coupon, (string) $data['coupon_code']);

            return redirect()->route('admin.plugins.store.coupons.index')
                ->with('success', 'Coupon deleted permanently.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    private function definition(Request $request): CouponDefinition
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9_-]{2,63}$/'],
            'discount_type' => ['required', 'in:percentage_discount,fixed_amount_discount'],
            'discount_value' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'minimum_order' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'target_scope' => ['required', 'in:all_catalog,product,category'],
            'product_ids' => ['nullable', 'array', 'max:500'],
            'product_ids.*' => ['string', 'size:26'],
            'category_ids' => ['nullable', 'array', 'max:500'],
            'category_ids.*' => ['string', 'size:26'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'active' => ['nullable', 'boolean'],
        ]);

        $targets = match ($data['target_scope']) {
            'product' => array_map(static fn (string $id): array => ['type' => 'product', 'public_id' => $id], $data['product_ids'] ?? []),
            'category' => array_map(static fn (string $id): array => ['type' => 'category', 'public_id' => $id], $data['category_ids'] ?? []),
            default => [['type' => 'all_catalog', 'public_id' => null]],
        };
        $percentage = $data['discount_type'] === 'percentage_discount'
            ? PercentageBasisPoints::fromPercentDecimal($data['discount_value'])->value
            : null;
        $fixed = $data['discount_type'] === 'fixed_amount_discount'
            ? Money::fromDecimal($data['discount_value'], (string) config('store.default_currency', 'JOD'))->amountMinor
            : null;

        return new CouponDefinition(
            $data['name'],
            $data['code'],
            $data['discount_type'],
            $fixed,
            $percentage,
            empty($data['minimum_order'])
                ? 0
                : Money::fromDecimal($data['minimum_order'], (string) config('store.default_currency', 'JOD'))->amountMinor,
            isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            isset($data['per_customer_limit']) ? (int) $data['per_customer_limit'] : null,
            $targets,
            empty($data['starts_at']) ? null : new DateTimeImmutable($data['starts_at']),
            empty($data['ends_at']) ? null : new DateTimeImmutable($data['ends_at']),
            $request->boolean('active'),
            $this->actor($request),
        );
    }
}
