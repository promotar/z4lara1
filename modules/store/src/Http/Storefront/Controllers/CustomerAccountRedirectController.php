<?php

namespace Modules\Store\Http\Storefront\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class CustomerAccountRedirectController
{
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect()->route('store.account.orders.index');
    }
}
