<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Store\Application\Inventory\InventoryService;
use Modules\Store\Domain\Inventory\InventoryTrackingMode;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Throwable;

final class InventoryWriteController extends Controller
{
    use HandlesStoreAdminRequests;

    public function adjust(Request $request, string $item, InventoryService $service): RedirectResponse
    {
        $data = $request->validate(['delta' => ['required', 'integer', 'not_in:0'], 'reason_code' => ['required', 'regex:/^[a-z0-9][a-z0-9_-]{0,127}$/'], 'idempotency_key' => ['required', 'string', 'max:191'], 'note' => ['nullable', 'string', 'max:2000']]);
        try {
            $service->adjust($item, (int) $data['delta'], $data['reason_code'], $data['idempotency_key'], $this->actor($request), $data['note'] ?? null);

            return back()->with('success', 'Inventory adjusted and immutable movement recorded.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function policy(Request $request, string $item, InventoryService $service): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:1'], 'tracking_mode' => ['required', 'in:tracked,untracked'], 'allow_backorder' => ['nullable', 'boolean'], 'safety_stock' => ['required', 'integer', 'min:0'], 'reorder_point' => ['nullable', 'integer', 'min:0']]);
        try {
            $service->updatePolicy($item, (int) $data['version'], InventoryTrackingMode::from($data['tracking_mode']), $request->boolean('allow_backorder'), (int) $data['safety_stock'], isset($data['reorder_point']) ? (int) $data['reorder_point'] : null);

            return back()->with('success', 'Inventory policy updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function reconcile(string $item, InventoryService $service): RedirectResponse
    {
        try {
            $result = $service->reconcile($item);
            $matches = $result->matches();
            $message = $matches ? 'Inventory ledger and balances reconcile.' : 'Inventory reconciliation mismatch detected; no automatic correction was made.';

            return back()->with($matches ? 'success' : 'warning', $message);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }
}
