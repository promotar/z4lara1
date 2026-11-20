<?php

namespace Modules\Store\Http\Admin\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Store\Application\Orders\OrderNotificationService;
use Modules\Store\Application\Orders\OrderReadService;
use Modules\Store\Application\Orders\OrderWorkflowService;
use Modules\Store\Application\Returns\ReturnWorkflowService;
use Modules\Store\Domain\Orders\OrderStatus;
use Modules\Store\Http\Admin\Controllers\Concerns\HandlesStoreAdminRequests;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class OrderAdminController extends Controller
{
    use HandlesStoreAdminRequests;

    public function index(Request $request, OrderReadService $orders): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:new,confirmed,processing,ready,shipped,delivered,cancelled,returned'],
            'cancellation_requested' => ['nullable', 'boolean'],
            'return_requested' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'payment_method' => ['nullable', 'in:cash_on_delivery'],
            'area' => ['nullable', 'string', 'max:128'],
            'min_total' => ['nullable', 'numeric', 'min:0'],
            'max_total' => ['nullable', 'numeric', 'gte:min_total'],
            'sort' => ['nullable', 'in:newest,oldest,highest,lowest'],
        ]);
        $canViewCustomer = $request->user()->can('store.orders.customer.view');
        $filters['_can_view_customer'] = $canViewCustomer;

        return view('store::admin.orders.index', [
            'orders' => $orders->paginate($filters),
            'counts' => $orders->counts(),
            'filters' => array_diff_key($filters, ['_can_view_customer' => true]),
            'statuses' => OrderStatus::cases(),
            'canViewCustomer' => $canViewCustomer,
        ]);
    }

    public function show(Request $request, string $order, OrderReadService $orders): View
    {
        $canUpdate = $request->user()->can('store.orders.update');

        return view('store::admin.orders.show', $orders->detail($order) + [
            'canViewCustomer' => $request->user()->can('store.orders.customer.view'),
            'canUpdate' => $canUpdate,
            'canOverride' => $canUpdate && $request->user()->can('store.orders.override'),
        ]);
    }

    public function transition(
        Request $request,
        string $order,
        OrderWorkflowService $workflow,
    ): RedirectResponse {
        $data = $request->validate([
            'status' => ['required', 'in:new,confirmed,processing,ready,shipped,delivered,cancelled,returned'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'override' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
            'cancellation_reason_code' => ['nullable', 'in:customer_request,out_of_stock,invalid_customer_data,unreachable_customer,undeliverable,other'],
            'return_condition' => ['nullable', 'in:resellable,damaged,opened,unknown'],
            'return_to_stock' => ['nullable', 'boolean'],
            'shipping_company' => ['nullable', 'string', 'max:255'],
            'courier_name' => ['nullable', 'string', 'max:255'],
            'courier_phone' => ['nullable', 'string', 'max:64'],
            'tracking_number' => ['nullable', 'string', 'max:128'],
            'shipping_notes' => ['nullable', 'string', 'max:2000'],
            'shipped_at' => ['nullable', 'date'],
            'delivered_at' => ['nullable', 'date'],
            'payment_collected' => ['nullable', 'boolean'],
            'payment_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $target = OrderStatus::from((string) $data['status']);
        if ($target === OrderStatus::Cancelled) {
            abort_unless($request->user()->can('store.orders.cancel'), 403);
        }
        if ($target === OrderStatus::Returned) {
            abort_unless($request->user()->can('store.orders.return'), 403);
        }
        $override = $request->boolean('override');
        if ($override) {
            abort_unless($request->user()->can('store.orders.override'), 403);
        }

        try {
            $workflow->transition($order, $target, $this->actor($request), $data, $override);

            return back()->with('success', "Order moved to {$target->label()}.");
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function note(Request $request, string $order, OrderWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:5000']]);

        try {
            $workflow->addNote($order, $data['note'], $this->actor($request));

            return back()->with('success', 'Internal note added.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function cancellationDecision(
        Request $request,
        string $order,
        OrderWorkflowService $workflow,
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
            'reason' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
            'prevent_further_appeals' => ['nullable', 'boolean'],
        ]);

        try {
            if ($data['decision'] === 'accept') {
                abort_unless($request->user()->can('store.orders.cancel'), 403);
                $workflow->acceptCancellationRequest($order, $this->actor($request));

                return back()->with('success', 'Customer cancellation request accepted. The order was cancelled.');
            }

            $workflow->rejectCancellationRequest(
                $order,
                (string) $data['reason'],
                $request->boolean('prevent_further_appeals'),
                $this->actor($request),
            );

            return back()->with('success', $request->boolean('prevent_further_appeals')
                ? 'Cancellation request rejected and further appeals were closed.'
                : 'Cancellation request rejected. The customer may submit another request.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function returnDecision(
        Request $request,
        string $order,
        ReturnWorkflowService $returns,
    ): RedirectResponse {
        $data = $request->validate([
            'action' => ['required', 'in:approve_process,request_more_evidence,authorize_return,reject,mark_received,start_inspection,accept_after_inspection,complete_resolution,record_refund_sent,close,expire'],
            'message' => ['nullable', 'string', 'max:3000'],
            'reason' => ['nullable', 'string', 'max:3000'],
            'return_instructions' => ['nullable', 'string', 'max:5000'],
            'return_address' => ['nullable', 'string', 'max:2000'],
            'shipping_deadline' => ['nullable', 'date', 'after:today'],
            'inspection_condition' => ['nullable', 'in:resellable,opened,damaged,missing_parts,not_as_described,unknown'],
            'inspection_notes' => ['nullable', 'string', 'max:5000'],
            'resolution_type' => ['nullable', 'in:full_refund,partial_refund,exchange,store_credit'],
            'refund_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_method' => ['nullable', 'string', 'max:64'],
            'refund_transfer_reference' => ['nullable', 'string', 'max:128'],
            'refund_transfer_note' => ['nullable', 'string', 'max:2000'],
            'restore_inventory' => ['nullable', 'boolean'],
        ]);

        try {
            abort_unless($request->user()->can('store.orders.return'), 403);
            $data['restore_inventory'] = $request->boolean('restore_inventory');
            $returns->adminAction($order, (string) $data['action'], $data, $this->actor($request));

            return back()->with('success', 'Return process updated successfully.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function returnAttachment(
        string $order,
        string $attachment,
        ReturnWorkflowService $returns,
    ): StreamedResponse {
        $file = $returns->attachmentForAdmin($order, $attachment);
        abort_unless(Storage::disk((string) $file->disk)->exists((string) $file->path), 404);

        return Storage::disk((string) $file->disk)->response(
            (string) $file->path,
            (string) $file->original_name,
            ['Content-Type' => (string) ($file->mime_type ?: 'application/octet-stream')],
        );
    }

    public function destroy(Request $request, string $order, OrderWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string', 'max:64'],
        ]);

        try {
            $orderNumber = $workflow->deleteCancelled($order, (string) $data['confirmation']);

            return redirect()->route('admin.plugins.store.orders.index')
                ->with('success', "Order {$orderNumber} was permanently deleted.");
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function payment(Request $request, string $order, OrderWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'payment_status' => ['required', 'in:cod_pending,paid,failed,cancelled'],
            'payment_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $workflow->updatePayment($order, $data, $this->actor($request));

            return back()->with('success', 'Payment status updated.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function shipment(Request $request, string $order, OrderWorkflowService $workflow): RedirectResponse
    {
        $data = $request->validate([
            'shipping_company' => ['nullable', 'string', 'max:255'],
            'courier_name' => ['nullable', 'string', 'max:255'],
            'courier_phone' => ['nullable', 'string', 'max:64'],
            'tracking_number' => ['nullable', 'string', 'max:128'],
            'shipping_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $workflow->updateShipment($order, $data, $this->actor($request));

            return back()->with('success', 'Shipment details saved.');
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function resend(string $order, OrderNotificationService $notifications): RedirectResponse
    {
        return $notifications->send($order, true)
            ? back()->with('success', 'Customer email sent.')
            : back()->withErrors(['store' => 'No customer email is available, or the message could not be sent.']);
    }

    public function contact(Request $request, string $order, OrderNotificationService $notifications): RedirectResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        try {
            return $notifications->sendContactRequest($order, $data['message'])
                ? back()->with('success', 'Customer contact request sent.')
                : back()->withErrors(['store' => 'No customer email is available, or the message could not be sent.']);
        } catch (Throwable $exception) {
            return $this->failure($exception);
        }
    }

    public function print(string $order, OrderReadService $orders): View
    {
        return view('store::admin.orders.print', $orders->detail($order));
    }
}
