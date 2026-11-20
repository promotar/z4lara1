<x-app-layout>
<main class="z4-store-admin" data-z4-store-foundation>
    <div class="z4-store-container">
        @include('store::admin._alerts')

        <header class="z4-store-heading">
            <div>
                <span class="z4-store-eyebrow">{{ __('store::ui.auto_bb71b09eb3') }}</span>
                <h1>{{ __('store::ui.auto_70719754b2') }}</h1>
                <p>{{ __('store::ui.auto_ee03128cac') }}</p>
            </div>
            <button class="z4-store-btn z4-store-btn-primary" type="button" data-toggle-panel="promotion-create"><span>＋</span> {{ __('store::ui.auto_1892ea99f8') }}</button>
        </header>

        <section class="z4-store-form-card z4-store-collapsible-form" id="promotion-create" hidden>
            <header class="z4-store-section-heading"><span>%</span><div><h2>{{ __('store::ui.auto_62e4b8cab2') }}</h2><p>{{ __('store::ui.auto_b0bc120269') }}</p></div></header>
            <form method="post" action="{{ route('admin.plugins.store.promotions.store') }}" class="z4-store-form-grid" data-dirty-form>
                @csrf
                <label class="is-wide"><span>{{ __('store::ui.auto_f8337c6de0') }} <b>*</b></span><input class="z4-store-input" name="name" required></label>
                <label><span>{{ __('store::ui.auto_650be61892') }}</span><select class="z4-store-select" name="mode"><option value="automatic">{{ __('store::ui.auto_086247a9b5') }}</option><option value="code">{{ __('store::ui.auto_ca0dbad92a') }}</option></select></label>
                <label><span>{{ __('store::ui.auto_ca0dbad92a') }}</span><input class="z4-store-input" name="code"></label>
                <label><span>{{ __('store::ui.auto_c41a318909') }}</span><select class="z4-store-select" name="target_type"><option value="all_catalog">{{ __('store::ui.auto_8ffdd435f1') }}</option><option value="category">{{ __('store::ui.auto_533a8c9cb1') }}</option><option value="product">{{ __('store::ui.auto_39a151f313') }}</option><option value="variant">{{ __('store::ui.auto_35e5a51b59') }}</option></select></label>
                <label><span>{{ __('store::ui.auto_51466863be') }}</span><input class="z4-store-input" name="target_public_id" maxlength="26"></label>
                <label class="z4-store-check"><input type="checkbox" name="include_descendants" value="1"><span><strong>{{ __('store::ui.auto_9d19368c18') }}</strong><small>{{ __('store::ui.auto_b11d8f4a97') }}</small></span></label>
                <label><span>{{ __('store::ui.auto_f908785bb6') }}</span><select class="z4-store-select" name="action_type"><option value="percentage_discount">{{ __('store::ui.auto_37be07209f') }}</option><option value="fixed_amount_discount">{{ __('store::ui.auto_7d9b42d9ba') }}</option></select></label>
                <label><span>{{ __('store::ui.auto_ac1910d0de') }} <b>*</b></span><input class="z4-store-input" name="discount_value" placeholder="10.00 or 5.000" required></label>
                <label><span>{{ __('store::ui.auto_34beb52ab4') }}</span><input class="z4-store-input" name="maximum_discount"></label>
                <label><span>{{ __('store::ui.auto_59f2f651f4') }}</span><select class="z4-store-select" name="stacking_policy"><option value="best_price">{{ __('store::ui.auto_2d8ee3c32f') }}</option><option value="exclusive">{{ __('store::ui.auto_2ef50b4c46') }}</option><option value="stackable">{{ __('store::ui.auto_2f926bb210') }}</option></select></label>
                <label><span>{{ __('store::ui.auto_161f25f902') }}</span><input type="datetime-local" class="z4-store-input" name="starts_at"></label>
                <label><span>{{ __('store::ui.auto_da22a18379') }}</span><input type="datetime-local" class="z4-store-input" name="ends_at"></label>
                <label><span>{{ __('store::ui.auto_502996d979') }}</span><input type="number" class="z4-store-input" name="priority" value="0"></label>
                <div class="z4-store-form-actions is-wide"><button class="z4-store-btn z4-store-btn-primary" data-submit-button>{{ __('store::ui.auto_625d3ee657') }}</button><button class="z4-store-btn z4-store-btn-ghost" type="button" data-toggle-panel="promotion-create">{{ __('store::ui.auto_ea4788705e') }}</button></div>
            </form>
        </section>

        <section class="z4-store-table-card">
            <header class="z4-store-card-header"><div><h2>{{ __('store::ui.auto_ccb44f203d') }}</h2><p>{{ __('store::ui.auto_d8fc283270') }}</p></div></header>
            <div class="z4-store-table-scroll">
                <table class="z4-store-table z4-store-table-compact">
                    <thead><tr><th>{{ __('store::ui.auto_626a54d37d') }}</th><th>{{ __('store::ui.auto_104d9898c0') }}</th><th>{{ __('store::ui.auto_91fadc5613') }}</th><th>{{ __('store::ui.auto_25f7347236') }}</th><th>{{ __('store::ui.auto_ec53a8c4f0') }}</th><th>{{ __('store::ui.auto_06df33001c') }}</th></tr></thead>
                    <tbody>
                    @forelse($promotions as $promotion)
                    <tr>
                        <td><strong>{{ $promotion->name }}</strong><small>{{ $promotion->code ?: ucfirst($promotion->mode) }} · {{ str_replace('_', ' ', $promotion->stacking_policy) }}</small></td>
                        <td><strong>{{ $promotion->action_type === 'percentage_discount' ? ($promotion->percentage_basis_points / 100).'%' : app(\Modules\Store\Application\Settings\StoreMoneyFormatter::class)->format((int) $promotion->amount_minor) }}</strong></td>
                        <td>{{ $promotion->targets_count }}</td>
                        <td><strong>{{ $promotion->starts_at ?? 'Starts now' }}</strong><small>{{ $promotion->ends_at ? 'Ends '.$promotion->ends_at : 'No end date' }}</small></td>
                        <td><span class="z4-store-status is-{{ $promotion->status === 'active' ? 'published' : ($promotion->status === 'archived' ? 'archived' : ($promotion->status === 'paused' ? 'hidden' : 'draft')) }}">{{ $promotion->status === 'archived' ? 'Deleted' : ucfirst($promotion->status) }}</span></td>
                        <td>
                            <details class="z4-store-actions-menu"><summary aria-label="{{ __('store::ui.auto_3401c3c3ac') }}">•••</summary><div>
                                <form method="post" action="{{ route('admin.plugins.store.promotions.lifecycle', $promotion->public_id) }}">
                                    @csrf<input type="hidden" name="version" value="{{ $promotion->version }}">
                                    @if(in_array($promotion->status, ['draft','paused']))<button name="action" value="activate">{{ __('store::ui.auto_a13367a8e2') }}</button>@endif
                                    @if($promotion->status === 'active')<button name="action" value="pause">{{ __('store::ui.auto_105b296a83') }}</button>@endif
                                    @if($promotion->status !== 'archived')<button name="action" value="archive" class="is-danger">{{ __('store::ui.auto_f2a6c498fb') }}</button>@endif
                                </form>
                            </div></details>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6"><div class="z4-store-empty"><span>%</span><strong>{{ __('store::ui.auto_7dd2ea8005') }}</strong><p>{{ __('store::ui.auto_b6765a68a6') }}</p></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <x-platform-pagination :paginator="$promotions" />
        </section>
    </div>
</main>
<script>
document.querySelectorAll('[data-toggle-panel]').forEach(button => button.addEventListener('click', () => {
    const panel = document.getElementById(button.dataset.togglePanel);
    panel.hidden = !panel.hidden;
}));
document.querySelectorAll('form').forEach(form => form.addEventListener('submit', () => {
    form.querySelectorAll('[data-submit-button]').forEach(button => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
    });
}));
</script>
</x-app-layout>
