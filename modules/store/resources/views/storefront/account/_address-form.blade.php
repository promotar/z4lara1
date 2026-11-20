<form class="z4-address-form" method="post" action="{{ $action }}">
    @csrf
    @if($method !== 'POST')@method($method)@endif
    <label><span>{{ __('store::ui.label') }}</span><input name="label" required maxlength="64" value="{{ old('label', $address?->label ?? __('store::ui.store_auto_8cf04a9734')) }}"></label>
    <label><span>{{ __('store::ui.recipient-name') }}</span><input name="recipient_name" required value="{{ old('recipient_name', $address?->recipient_name ?? auth()->user()->name) }}"></label>
    <label><span>{{ __('store::ui.phone') }}</span><input name="phone" required value="{{ old('phone', $address?->phone) }}"></label>
    <input type="hidden" name="country_code" value="{{ old('country_code', $address?->country_code ?? 'JO') }}">
    <label><span>{{ __('store::ui.governorate') }}</span><input name="governorate" value="{{ old('governorate', $address?->governorate) }}"></label>
    <label><span>{{ __('store::ui.city') }}</span><input name="city" required value="{{ old('city', $address?->city) }}"></label>
    <label><span>{{ __('store::ui.area') }}</span><input name="area" value="{{ old('area', $address?->area) }}"></label>
    <label class="is-wide"><span>{{ __('store::ui.address') }}</span><input name="address_line_1" required value="{{ old('address_line_1', $address?->street) }}"></label>
    <label><span>{{ __('store::ui.postal-code') }}</span><input name="postal_code" value="{{ old('postal_code', $address?->postal_code) }}"></label>
    <label class="is-wide z4sf-check"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address?->is_default ?? false))><span>{{ __('store::ui.use-as-default-shipping-address') }}</span></label>
    <button class="z4-account-primary-button is-wide">{{ $button }}</button>
</form>
