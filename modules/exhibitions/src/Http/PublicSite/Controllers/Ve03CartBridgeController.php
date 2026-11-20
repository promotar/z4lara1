<?php

namespace Modules\Exhibition\Http\PublicSite\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

final class Ve03CartBridgeController extends Controller
{
    private const STORE_CART_SERVICE = 'Modules\\Store\\Application\\Storefront\\CartService';

    public function summary(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function add(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_public_id' => ['required', 'string', 'size:26'],
            'source' => ['nullable', 'array'],
        ]);
        $variant = (string) $data['variant_public_id'];
        $existing = (array) $request->session()->get('store.cart.items', []);

        if (array_key_exists($variant, $existing)) {
            return response()->json([
                'outcome' => 'already_in_cart',
                'message' => __('This artwork is already in your cart. You can keep exploring.'),
                'cart' => $this->snapshot($request),
            ]);
        }

        try {
            $this->cart()->add($request->session(), $variant, 1);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => __($exception->getMessage())], 422);
        }

        if (is_array($data['source'] ?? null)) {
            $request->session()->put('store.cart.purchase_sources.'.$variant, $data['source']);
        }

        return response()->json([
            'outcome' => 'added',
            'message' => __('Artwork added. Keep exploring or open the cart summary.'),
            'cart' => $this->snapshot($request),
        ]);
    }

    public function remove(Request $request, string $variant): JsonResponse
    {
        abort_unless(strlen($variant) === 26, 404);
        $this->cart()->remove($request->session(), $variant);
        $request->session()->forget('store.cart.purchase_sources.'.$variant);

        return response()->json([
            'message' => __('Artwork removed from cart.'),
            'cart' => $this->snapshot($request),
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        abort_unless(Route::has('store.checkout.index'), 404);
        $data = $request->validate([
            'return_context' => ['nullable', 'array'],
            'return_context.owner_code' => ['required_with:return_context', 'in:exhibitions'],
            'return_context.type' => ['required_with:return_context', 'in:virtual_exhibition'],
            'return_context.reference' => ['required_with:return_context', 'string', 'max:64'],
            'return_context.return_path' => ['required_with:return_context', 'string', 'max:512'],
            'return_context.state_json' => ['required_with:return_context', 'string', 'max:4096'],
        ]);
        if (is_array($data['return_context'] ?? null)) {
            $request->session()->put(
                'store.checkout.return_context',
                $this->normalizeReturnContext($data['return_context']),
            );
        }

        return response()->json(['checkout_url' => route('store.checkout.index')]);
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function normalizeReturnContext(array $context): array
    {
        $returnPath = (string) $context['return_path'];
        if (! str_starts_with($returnPath, '/exhibitions/virtual/')
            || str_starts_with($returnPath, '//')
            || str_contains($returnPath, '\\')) {
            throw ValidationException::withMessages([
                'return_context.return_path' => __('The exhibition return path is invalid.'),
            ]);
        }

        try {
            $state = json_decode((string) $context['state_json'], true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $state = null;
        }
        if (! is_array($state)
            || ! is_string($state['environment_version_id'] ?? null)
            || ! is_string($state['room_code'] ?? null)
            || ! $this->validPosition($state['position'] ?? null)) {
            throw ValidationException::withMessages([
                'return_context.state_json' => __('The exhibition return state is invalid.'),
            ]);
        }

        return [
            'owner_code' => 'exhibitions',
            'type' => 'virtual_exhibition',
            'reference' => (string) $context['reference'],
            'return_path' => $returnPath,
            'state' => [
                'environment_version_id' => $state['environment_version_id'],
                'room_code' => $state['room_code'],
                'position' => array_map('floatval', $state['position']),
                'yaw' => is_numeric($state['yaw'] ?? null) ? (float) $state['yaw'] : 0.0,
                'pitch' => is_numeric($state['pitch'] ?? null) ? (float) $state['pitch'] : 0.0,
            ],
        ];
    }

    private function validPosition(mixed $position): bool
    {
        return is_array($position)
            && count($position) === 3
            && collect($position)->every(static fn (mixed $coordinate): bool => is_numeric($coordinate)
                && is_finite((float) $coordinate));
    }

    private function cart(): object
    {
        abort_unless(class_exists(self::STORE_CART_SERVICE), 404);

        return app(self::STORE_CART_SERVICE);
    }

    /** @return array{count: int, items: array<int, array<string, mixed>>, subtotal: string} */
    private function snapshot(Request $request): array
    {
        $snapshot = $this->cart()->snapshot($request->session(), $request->user()?->id);
        $items = collect($snapshot['items'] ?? [])->map(static fn (array $item): array => [
            'variant_public_id' => (string) ($item['variant_public_id'] ?? ''),
            'title' => (string) ($item['product_name'] ?? __('Artwork')),
            'artist' => (string) ($item['artist_display_name'] ?? __('Artist')),
            'price' => (string) ($item['line_total_formatted'] ?? $item['price_formatted'] ?? ''),
            'image_url' => $item['image_url'] ?? null,
            'availability' => ($item['available'] ?? false) ? 'available' : 'unavailable',
        ])->values()->all();

        return [
            'count' => (int) ($snapshot['count'] ?? count($items)),
            'items' => $items,
            'subtotal' => (string) ($snapshot['subtotal_formatted'] ?? '0.000 JOD'),
        ];
    }
}
