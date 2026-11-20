<x-app-layout>
<main class="z4-store-admin" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._navigation')
        @include('store::admin._alerts')
        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_065ab3a28c') }}</span>
                <h1>{{ __('store::ui.auto_662a805daf') }}</h1>
                <p>{{ __('store::ui.auto_9912ef08b7') }}</p>
            </div>
            <a class="z4-store-btn z4-store-btn-primary" href="{{ route('admin.plugins.store.shipping-zones.create') }}">{{ __('store::ui.auto_e392c3d953') }}</a>
        </header>

        <section class="z4-store-table-card">
            <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_bb82088e06') }}</h2><p>More specific geographic matches win; priority resolves ties.</p></div></header>
            <div class="z4-store-table-scroll">
                <table class="z4-store-table">
                    <thead><tr><th>{{ __('store::ui.auto_b3ff996fe5') }}</th><th>{{ __('store::ui.auto_eebd338ddb') }}</th><th>{{ __('store::ui.auto_20b4f77bd0') }}</th><th>{{ __('store::ui.auto_cd710d566d') }}</th><th>{{ __('store::ui.auto_2ce37c9270') }}</th><th>{{ __('store::ui.auto_502996d979') }}</th><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th>{{ __('store::ui.auto_06df33001c') }}</th></tr></thead>
                    <tbody>
                    @forelse($zones as $zone)
                        <tr>
                            <td><strong>{{ $zone->name }}</strong></td>
                            <td>{{ __('store::ui.auto_2bf0a5172c') }}</td>
                            <td>{{ app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int)$zone->delivery_cost_minor) }}</td>
                            <td>{{ $zone->free_shipping_threshold_minor === null ? 'Not configured' : 'From '.app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int)$zone->free_shipping_threshold_minor) }}</td>
                            <td>{{ $zone->estimated_delivery ?: 'Not specified' }}</td>
                            <td><strong>{{ $zone->priority }}</strong></td>
                            <td><span class="z4-store-status is-{{ $zone->status === 'active' ? 'published' : 'hidden' }}">{{ ucfirst($zone->status) }}</span></td>
                            <td>
                                <details class="z4-store-actions-menu"><summary aria-label="{{ __('store::ui.auto_86ccd325d2') }}">...</summary><div>
                                    <a href="{{ route('admin.plugins.store.shipping-zones.edit', $zone->public_id) }}">{{ __('store::ui.auto_7dce122004') }}</a>
                                    <form method="post" action="{{ route('admin.plugins.store.shipping-zones.status', $zone->public_id) }}">
                                        @csrf
                                        <input type="hidden" name="version" value="{{ $zone->version }}">
                                        <input type="hidden" name="active" value="{{ $zone->status === 'active' ? 0 : 1 }}">
                                        <button>{{ $zone->status === 'active' ? 'Disable' : 'Activate' }}</button>
                                    </form>
                                    <form method="post"
                                          action="{{ route('admin.plugins.store.shipping-zones.destroy', $zone->public_id) }}"
                                          data-z4-confirm
                                          data-confirm-title="{{ __('store::ui.auto_6fa404f585') }}"
                                          data-confirm-message="This permanently removes the shipping zone and its geographic coverage rules. Existing orders remain unchanged."
                                          data-confirm-label="Delete shipping zone"
                                          data-confirm-require="{{ $zone->name }}"
                                          data-confirm-input="zone_name"
                                          data-confirm-verification-label="Enter the shipping zone name to confirm"
                                          data-confirm-input-label="Shipping zone name confirmation"
                                          data-confirm-placeholder="{{ __('store::ui.auto_8fd77a1b4d') }}"
                                          data-confirm-error="The shipping zone name does not match."
                                          data-confirm-tone="danger">
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="version" value="{{ $zone->version }}">
                                        <input type="hidden" name="zone_name" value="">
                                        <button class="is-danger" type="submit">{{ __('store::ui.auto_f2a6c498fb') }}</button>
                                    </form>
                                </div></details>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><div class="z4-store-empty"><span>→</span><strong>{{ __('store::ui.auto_31a8b1499e') }}</strong><p>{{ __('store::ui.auto_a0372d1373') }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-platform-pagination :paginator="$zones" />
        </section>
    </div>
</main>
</x-app-layout>
