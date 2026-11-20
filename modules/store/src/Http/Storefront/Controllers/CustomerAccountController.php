<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Store\Application\Customer\CustomerAddressService;
use Modules\Store\Application\Customer\CustomerOrderService;
use Modules\Store\Application\Customer\CustomerProfileService;
use Modules\Store\Application\Customer\CustomerResourceNotFound;
use Modules\Store\Application\Returns\ReturnWorkflowService;
use Modules\Store\Application\Reviews\ProductReviewService;
use Modules\Store\Application\Storefront\CartService;
use Modules\Store\Application\Storefront\StorefrontLocale;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CustomerAccountController extends Controller
{
    public function __construct(
        private readonly CustomerOrderService $orders,
        private readonly CustomerAddressService $addresses,
        private readonly CustomerProfileService $profiles,
        private readonly CartService $cart,
        private readonly StorefrontLocale $locale,
        private readonly ProductReviewService $reviews,
        private readonly ReturnWorkflowService $returns,
    ) {}

    public function orders(Request $request): View
    {
        $this->locale->apply();
        $filter = $request->validate(['filter' => ['nullable', 'in:all,current,completed,cancelled,returned']])['filter'] ?? 'all';
        $orders = $this->orders->paginate((int) $request->user()->id, $filter);

        return view('store::storefront.account.orders', [
            'orders' => $orders,
            'filter' => $filter,
            'customerProfile' => $this->profiles->find((int) $request->user()->id),
            'cartCount' => $this->cart->count($request->session()),
        ]);
    }

    public function overview(Request $request): View
    {
        $this->locale->apply();
        $orders = $this->orders->paginate((int) $request->user()->id);
        $latestOrder = $orders->getCollection()->first();
        $addresses = $this->addresses->list((int) $request->user()->id);

        return view('store::storefront.account.overview', [
            'latestOrder' => $latestOrder,
            'ordersCount' => $orders->total(),
            'addressesCount' => $addresses->count(),
            'defaultAddress' => $addresses->firstWhere('is_default', true) ?? $addresses->first(),
            'customerProfile' => $this->profiles->find((int) $request->user()->id),
            'cartCount' => $this->cart->count($request->session()),
        ]);
    }

    public function show(Request $request, string $order): View
    {
        $this->locale->apply();

        try {
            return view('store::storefront.account.order', $this->orders->detail((int) $request->user()->id, $order) + [
                'customerProfile' => $this->profiles->find((int) $request->user()->id),
                'cartCount' => $this->cart->count($request->session()),
            ]);
        } catch (CustomerResourceNotFound) {
            abort(404);
        }
    }

    public function reorder(Request $request, string $order): RedirectResponse
    {
        try {
            $result = $this->orders->reorder((int) $request->user()->id, $order, $request->session());
        } catch (CustomerResourceNotFound) {
            abort(404);
        }
        if ($result['added'] === []) {
            return back()->withErrors(['reorder' => __('store::ui.store_auto_b5b5b6fb3c')]);
        }

        $response = redirect()->route('store.cart.index')
            ->with('store_success', __('store::ui.store_auto_d431c3c057'));
        if ($result['unavailable'] !== []) {
            $response->withErrors([
                'reorder' => __('store::ui.store_auto_538e2009e1', [
                    'products' => implode(', ', $result['unavailable']),
                ]),
            ]);
        }

        return $response;
    }

    public function cancel(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->orders->cancel(
                (int) $request->user()->id,
                $order,
                $data['reason'],
                (string) $request->user()->name,
            );

            return back()->with('store_success', __('store::ui.store_auto_ac3bc74331'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['cancel' => $exception->getMessage()]);
        }
    }

    public function requestCancellation(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        try {
            $this->orders->requestCancellation(
                (int) $request->user()->id,
                $order,
                $data['reason'],
                (string) $request->user()->name,
            );

            return back()->with('store_success', __('store::ui.store_auto_312dfa2970'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['cancel' => $exception->getMessage()]);
        }
    }

    public function requestReturn(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate([
            'condition' => ['required', 'in:resellable,opened,damaged,unknown'],
            'reason' => ['required', 'string', 'max:2000'],
            'preferred_resolution' => ['required', 'in:refund,exchange,store_credit'],
        ]);

        try {
            $this->orders->requestReturn(
                (int) $request->user()->id,
                $order,
                $data['reason'],
                $data['condition'],
                $data['preferred_resolution'],
                (string) $request->user()->name,
            );

            return back()->with('store_success', __('store::ui.store_auto_1905109e7b'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }
    }

    public function submitReturnEvidence(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate([
            'photos' => ['nullable', 'array', 'max:5', 'required_without:video'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'video' => ['nullable', 'file', 'mimes:mp4,webm,mov', 'max:51200', 'required_without:photos'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->returns->submitEvidence(
                (int) $request->user()->id,
                $order,
                $request->file('photos', []),
                $request->file('video'),
                $data['message'] ?? null,
                (string) $request->user()->name,
            );

            return back()->with('store_success', __('store::ui.store_auto_85ff26a50f'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }
    }

    public function submitReturnShipment(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate([
            'carrier' => ['required', 'string', 'max:128'],
            'tracking_number' => ['required', 'string', 'max:128'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        try {
            $this->returns->submitShipment(
                (int) $request->user()->id,
                $order,
                $data['carrier'],
                $data['tracking_number'],
                $request->file('receipt'),
                (string) $request->user()->name,
            );

            return back()->with('store_success', __('store::ui.store_auto_938ec59bcb'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }
    }

    public function cancelReturn(Request $request, string $order): RedirectResponse
    {
        try {
            $this->returns->cancelByCustomer(
                (int) $request->user()->id,
                $order,
                (string) $request->user()->name,
            );

            return back()->with('store_success', __('store::ui.store_auto_cb3525d01b'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }
    }

    public function submitRefundDestination(Request $request, string $order): RedirectResponse
    {
        $data = $request->validate([
            'refund_destination_method' => ['required', 'in:bank_transfer,cliq'],
            'beneficiary_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:64'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'cliq_alias' => ['nullable', 'string', 'max:128'],
            'cliq_phone' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $this->returns->submitRefundDestination(
                (int) $request->user()->id,
                $order,
                $data,
                (string) $request->user()->name,
            );

            return back()->with('store_success', __('store::ui.store_auto_530f1a0b00'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }
    }

    public function confirmRefundReceived(Request $request, string $order): RedirectResponse
    {
        $request->validate([
            'refund_received_confirmation' => ['required', 'accepted'],
        ]);

        try {
            $this->returns->confirmRefundReceived(
                (int) $request->user()->id,
                $order,
                (string) $request->user()->name,
            );

            return back()->with('store_success', __('store::ui.store_auto_9369ca3133'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['return' => $exception->getMessage()]);
        }
    }

    public function returnAttachment(Request $request, string $order, string $attachment): StreamedResponse
    {
        try {
            $file = $this->returns->attachmentForCustomer((int) $request->user()->id, $order, $attachment);
        } catch (CustomerResourceNotFound) {
            abort(404);
        }
        abort_unless(Storage::disk((string) $file->disk)->exists((string) $file->path), 404);

        return Storage::disk((string) $file->disk)->response(
            (string) $file->path,
            (string) $file->original_name,
            ['Content-Type' => (string) ($file->mime_type ?: 'application/octet-stream')],
        );
    }

    public function storeReview(Request $request, string $order, string $item): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $this->reviews->create(
                (int) $request->user()->id,
                (string) $request->user()->name,
                $order,
                $item,
                $data,
            );

            return back()->with('store_success', __('store::ui.store_auto_90ca5ecbe2'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['review' => $exception->getMessage()]);
        }
    }

    public function invoice(Request $request, string $order): StreamedResponse
    {
        try {
            $record = $this->orders->ownedOrder((int) $request->user()->id, $order);
        } catch (CustomerResourceNotFound) {
            abort(404);
        }
        abort_if($record->invoice_path === null || ! Storage::disk('local')->exists((string) $record->invoice_path), 404);

        return Storage::disk('local')->download(
            (string) $record->invoice_path,
            ((string) ($record->invoice_number ?: $record->order_number)).'.pdf',
        );
    }

    public function addresses(Request $request): View
    {
        $this->locale->apply();

        return view('store::storefront.account.addresses', [
            'addresses' => $this->addresses->list((int) $request->user()->id),
            'customerProfile' => $this->profiles->find((int) $request->user()->id),
            'cartCount' => $this->cart->count($request->session()),
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $this->addresses->create((int) $request->user()->id, $this->addressData($request));

        return back()->with('store_success', __('store::ui.store_auto_0c0b44393f'));
    }

    public function updateAddress(Request $request, string $address): RedirectResponse
    {
        try {
            $this->addresses->update((int) $request->user()->id, $address, $this->addressData($request));

            return back()->with('store_success', __('store::ui.store_auto_2fe657fd85'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        }
    }

    public function defaultAddress(Request $request, string $address): RedirectResponse
    {
        try {
            $this->addresses->setDefault((int) $request->user()->id, $address);

            return back()->with('store_success', __('store::ui.store_auto_dd276933b6'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        }
    }

    public function destroyAddress(Request $request, string $address): RedirectResponse
    {
        try {
            $this->addresses->delete((int) $request->user()->id, $address);

            return back()->with('store_success', __('store::ui.store_auto_a23cfdef05'));
        } catch (CustomerResourceNotFound) {
            abort(404);
        }
    }

    public function personal(Request $request): View
    {
        $this->locale->apply();

        return view('store::storefront.account.personal', [
            'user' => $request->user(),
            'customerProfile' => $this->profiles->find((int) $request->user()->id),
            'cartCount' => $this->cart->count($request->session()),
        ]);
    }

    public function updatePersonal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:64'],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=120,min_height=120,max_width=5000,max_height=5000'],
            'remove_avatar' => ['nullable', 'boolean'],
        ]);

        $this->profiles->update((int) $request->user()->id, $data, $request->file('avatar'));

        return back()->with('store_success', __('store::ui.store_auto_c82f931cc0'));
    }

    public function avatar(Request $request): StreamedResponse
    {
        $profile = $this->profiles->find((int) $request->user()->id);
        abort_if($profile->avatar_path === null || ! Storage::disk('local')->exists((string) $profile->avatar_path), 404);

        return Storage::disk('local')->response((string) $profile->avatar_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** @return array<string, mixed> */
    private function addressData(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:64'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:64'],
            'country_code' => ['required', 'string', 'size:2'],
            'governorate' => ['nullable', 'string', 'max:128'],
            'city' => ['required', 'string', 'max:128'],
            'area' => ['nullable', 'string', 'max:128'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }
}
