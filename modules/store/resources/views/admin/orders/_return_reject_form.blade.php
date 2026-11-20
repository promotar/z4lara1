<form method="post" action="{{ route('admin.plugins.store.orders.return-request.decision', $order->public_id) }}">
    @csrf
    <input type="hidden" name="action" value="reject">
    <h3>{{ __('store::ui.auto_e7eff02a81') }}</h3>
    <p>{{ __('store::ui.auto_775b474790') }}</p>
    <label><span>{{ __('store::ui.auto_d499a5a986') }}</span><textarea class="z4-store-textarea" name="reason" rows="4" maxlength="3000" required placeholder="Explain which evidence is insufficient and why the return cannot proceed."></textarea></label>
    <button class="z4-store-btn z4-store-btn-ghost" type="submit">{{ __('store::ui.auto_52976fb725') }}</button>
</form>
