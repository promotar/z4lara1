<?php

namespace Modules\Store\Http\Admin\Controllers;

use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Application\Promotions\CreatePromotionCommand;
use Modules\Store\Application\Promotions\PromotionActionInput;
use Modules\Store\Application\Promotions\PromotionService;
use Modules\Store\Application\Promotions\PromotionTargetInput;
use Modules\Store\Domain\Promotions\PercentageBasisPoints;
use Modules\Store\Domain\Promotions\PromotionActionType;
use Modules\Store\Domain\Promotions\PromotionStackingPolicy;
use Modules\Store\Domain\Shared\Money;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class PromotionWriteController extends Controller
{
    use HandlesStoreAdminRequests;

    public function store(Request $request, PromotionService $service): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'mode' => ['required', 'in:automatic,code'], 'code' => ['nullable', 'string', 'max:128'],
            'stacking_policy' => ['required', 'in:exclusive,stackable,best_price'], 'priority' => ['nullable', 'integer', 'between:-100000,100000'],
            'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'target_type' => ['required', 'in:all_catalog,category,product,variant'], 'target_public_id' => ['nullable', 'string', 'size:26'], 'include_descendants' => ['nullable', 'boolean'],
            'action_type' => ['required', 'in:percentage_discount,fixed_amount_discount'], 'discount_value' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'], 'maximum_discount' => ['nullable', 'regex:/^\d+(?:\.\d{1,3})?$/'],
        ]);
        try {
            $type = PromotionActionType::from($data['action_type']);
            $fixed = $type === PromotionActionType::FixedAmountDiscount ? Money::fromDecimal($data['discount_value'], 'JOD')->amountMinor : null;
            $percentage = $type === PromotionActionType::PercentageDiscount
                ? PercentageBasisPoints::fromPercentDecimal($data['discount_value'])->value
                : null;
            $maximum = isset($data['maximum_discount']) ? Money::fromDecimal($data['maximum_discount'], 'JOD')->amountMinor : null;
            $service->create(new CreatePromotionCommand(
                $data['name'], $data['mode'], PromotionStackingPolicy::from($data['stacking_policy']),
                [new PromotionTargetInput($data['target_type'], $data['target_type'] === 'all_catalog' ? null : ($data['target_public_id'] ?? null), $request->boolean('include_descendants'))],
                [], new PromotionActionInput($type, $fixed, $percentage, $fixed === null ? null : 'JOD', $maximum),
                $this->actor($request), $data['code'] ?? null, (int) ($data['priority'] ?? 0),
                empty($data['starts_at']) ? null : new DateTimeImmutable($data['starts_at']), empty($data['ends_at']) ? null : new DateTimeImmutable($data['ends_at']),
            ));

            return back()->with('success', 'Promotion created as draft. Activate it after reviewing its target and schedule.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function lifecycle(Request $request, string $promotion, PromotionService $service): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'action' => ['required', 'in:activate,pause,archive']]);
        try {
            $service->{$data['action']}($promotion, (int) $data['version'], $this->actor($request));

            return back()->with('success', 'Promotion lifecycle updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
