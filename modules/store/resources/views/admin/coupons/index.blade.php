<x-app-layout>
<main class="z4-store-admin" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_6fc1d88019') }}</span>
                <h1>{{ __('store::ui.auto_cc4616c718') }}</h1>
                <p>{{ __('store::ui.auto_71dff3510c') }}</p>
            </div>
            <a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.coupons.create') }}">{{ __('store::ui.auto_d2625782ef') }}</a>
        </header>

        <section class="z4-store-table-card">
            <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_7a23940baa') }}</h2><p>{{ __('store::ui.auto_7b688fd2ac') }}</p></div></header>
            <div class="z4-store-table-scroll">
                <table class="z4-store-table">
                    <thead><tr><th>{{ __('store::ui.auto_adb6e651c4') }}</th><th>{{ __('store::ui.auto_ca0dbad92a') }}</th><th>{{ __('store::ui.auto_104d9898c0') }}</th><th>{{ __('store::ui.auto_b07cc28016') }}</th><th>{{ __('store::ui.auto_c64518704c') }}</th><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th>{{ __('store::ui.auto_06df33001c') }}</th></tr></thead>
                    <tbody>
                    @forelse($coupons as $coupon)
                        <tr>
                            <td><strong>{{ $coupon->name }}</strong></td>
                            <td><code>{{ $coupon->code }}</code></td>
                            <td><strong>{{ $coupon->action_type === 'percentage_discount' ? number_format($coupon->percentage_basis_points / 100, 2).'%' : app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int)$coupon->amount_minor) }}</strong></td>
                            <td><small>{{ $coupon->starts_at ?: 'Starts immediately' }}</small><small>{{ $coupon->ends_at ?: 'No end date' }}</small></td>
                            <td><strong>{{ $coupon->usage_count }}</strong><small>{{ $coupon->usage_limit === null ? 'No total limit' : 'of '.$coupon->usage_limit }}</small></td>
                            <td><span class="z4-store-status is-{{ $coupon->status === 'active' ? 'published' : 'hidden' }}">{{ ucfirst($coupon->status) }}</span></td>
                            <td>
                                <details class="z4-store-actions-menu"><summary aria-label="{{ __('store::ui.auto_1b36b5cb7f') }}">...</summary><div>
                                    <a href="{{ route('admin.plugins.store.coupons.edit', $coupon->public_id) }}">{{ __('store::ui.auto_7dce122004') }}</a>
                                    <form method="post" action="{{ route('admin.plugins.store.coupons.status', $coupon->public_id) }}">
                                        @csrf
                                        <input type="hidden" name="version" value="{{ $coupon->version }}">
                                        <input type="hidden" name="active" value="{{ $coupon->status === 'active' ? 0 : 1 }}">
                                        <button>{{ $coupon->status === 'active' ? 'Disable' : 'Activate' }}</button>
                                    </form>
                                    <form method="post"
                                          action="{{ route('admin.plugins.store.coupons.destroy', $coupon->public_id) }}"
                                          data-z4-confirm
                                          data-confirm-title="{{ __('store::ui.auto_2401b57611') }}"
                                          data-confirm-message="This permanently removes the coupon and its eligibility rules. Coupons with order history cannot be deleted."
                                          data-confirm-label="Delete coupon"
                                          data-confirm-require="{{ $coupon->code }}"
                                          data-confirm-input="coupon_code"
                                          data-confirm-verification-label="Enter the coupon code to confirm"
                                          data-confirm-input-label="Coupon code confirmation"
                                          data-confirm-placeholder="{{ __('store::ui.auto_b80c0f8564') }}"
                                          data-confirm-error="The coupon code does not match."
                                          data-confirm-tone="danger">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="coupon_code" value="">
                                        <button class="is-danger" type="submit">{{ __('store::ui.auto_f2a6c498fb') }}</button>
                                    </form>
                                </div></details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><div class="z4-store-empty"><span>%</span><strong>{{ __('store::ui.auto_4361c05617') }}</strong><p>{{ __('store::ui.auto_ca90282e2b') }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-platform-pagination :paginator="$coupons" />
        </section>
    </div>
</main>
</x-app-layout>
