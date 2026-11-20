<?php

namespace Modules\Store\Http\Admin\Controllers\Concerns;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Store\Domain\Shared\ActorContext;
use Modules\Store\Domain\Shared\Exceptions\StoreDomainException;
use Throwable;

trait HandlesStoreAdminRequests
{
    private function actor(Request $request): ActorContext
    {
        $user = $request->user();

        return new ActorContext(
            'user',
            (string) $user->getAuthIdentifier(),
            trim((string) ($user->name ?? '')) ?: null,
        );
    }

    private function failure(Throwable $exception): RedirectResponse
    {
        if ($exception instanceof InvalidArgumentException || $exception instanceof StoreDomainException) {
            return back()->withErrors(['store' => $exception->getMessage()])->withInput();
        }
        if ($exception instanceof UniqueConstraintViolationException) {
            $message = $exception->getMessage();
            $errors = match (true) {
                str_contains($message, 'store_product_translations_locale_slug_unique') => ['slug' => 'This product URL is already used. Choose a different slug.'],
                str_contains($message, 'store_products_internal_code_unique') => ['internal_code' => 'This internal code is already used by another product.'],
                str_contains($message, 'store_product_variants_sku_unique') => ['sku' => 'This SKU is already used by another product or variant.'],
                str_contains($message, 'store_product_variants_barcode_unique') => ['barcode' => 'This barcode is already used by another variant.'],
                str_contains($message, 'store_product_variants_combination_unique') => ['options' => 'This option combination already exists for the product.'],
                str_contains($message, 'store_product_variants_one_default_unique') => ['is_default' => 'This product already has a default variant.'],
                default => ['store' => 'A catalog value is already used. Review the highlighted product URL, SKU, internal code, or barcode fields.'],
            };

            return back()->withErrors($errors)->withInput();
        }

        throw $exception;
    }
}
