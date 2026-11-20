<x-frontend-layout>
    <div class="z4sf z4-account z4-account-v2" data-z4-store dir="{{ $platformDirection ?? app(\App\Platform\Core\Localization\PlatformLocale::class)->direction() }}">
        <main class="z4sf-container z4-account-shell-v2">
            @include('store::storefront.account._alerts')
            <nav class="z4-account-breadcrumb" aria-label="{{ __('store::ui.breadcrumb') }}">
                <a href="{{ route('store.index') }}">{{ __('store::ui.home') }}</a><span aria-hidden="true">›</span><span>{{ __('store::ui.store-account') }}</span><span aria-hidden="true">›</span><span>{{ __('store::ui.profile-security') }}</span>
            </nav>
            <h1 class="z4-account-title">{{ __('store::ui.store-account') }}</h1>

            <div class="z4-account-frame">
                @include('store::storefront.account._sidebar')
                <div class="z4-account-content">
                    <section class="z4-account-section">
                        <header class="z4-account-section-heading">
                            <div><p>{{ __('store::ui.store-profile') }}</p><h2>{{ __('store::ui.profile-security') }}</h2><span>{{ __('store::ui.manage-the-phone-number-and-profile-photo-used-by-the-store') }}</span></div>
                        </header>

                        <form class="z4-account-profile-form" method="post" action="{{ route('store.account.personal.update') }}" enctype="multipart/form-data" data-profile-form>
                            @csrf
                            @method('put')

                            <div class="z4-account-photo-editor">
                                <div class="z4-account-photo-preview" data-photo-preview>
                                    @if(($customerProfile->avatar_path ?? null) !== null)
                                        <img src="{{ route('store.account.avatar', ['v' => strtotime((string) $customerProfile->updated_at)]) }}" alt="{{ __('store::ui.store_auto_b375544556') }}" data-current-photo>
                                    @else
                                        <span data-photo-initial>{{ mb_strtoupper(mb_substr(trim((string) $user->name), 0, 1)) }}</span>
                                    @endif
                                </div>
                                <div class="z4-account-photo-actions">
                                    <strong>{{ __('store::ui.profile-photo') }}</strong>
                                    <p>{{ __('store::ui.jpg-png-or-webp-maximum-5-mb') }}</p>
                                    <div>
                                        <label class="z4-account-secondary-button" for="profile-avatar">{{ __('store::ui.choose-photo') }}</label>
                                        <input id="profile-avatar" class="z4-account-file-input" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-photo-input>
                                        @if(($customerProfile->avatar_path ?? null) !== null)
                                            <label class="z4-account-remove-photo"><input type="checkbox" name="remove_avatar" value="1"> {{ __('store::ui.remove-current-photo') }}</label>
                                        @endif
                                    </div>
                                    @error('avatar')<span class="z4-account-field-error">{{ $message }}</span>@enderror
                                </div>
                            </div>

                            <div class="z4-account-form-grid">
                                <label><span>{{ __('store::ui.full-name') }}</span><input type="text" value="{{ $user->name }}" readonly aria-readonly="true"></label>
                                <label><span>{{ __('store::ui.email-address') }}</span><input type="email" value="{{ $user->email }}" readonly aria-readonly="true"></label>
                                <label class="is-full"><span>{{ __('store::ui.phone-number') }}</span><input type="tel" name="phone" value="{{ old('phone', $customerProfile->phone) }}" autocomplete="tel" placeholder="e.g. +962 79 000 0000">@error('phone')<small class="z4-account-field-error">{{ $message }}</small>@enderror</label>
                            </div>

                            <div class="z4-account-password-fields">
                                <strong>{{ __('store::ui.central-account') }}</strong>
                                <p>{{ __('store::ui.name-email-and-password-are-managed-by-your-main-platform-ac') }}</p>
                                <a class="z4-account-secondary-button" href="{{ route('profile.edit') }}">{{ __('store::ui.manage-main-account') }}</a>
                            </div>

                            <button class="z4-account-primary-button" type="submit">{{ __('store::ui.save-store-profile') }}</button>
                        </form>
                    </section>

                </div>
            </div>
        </main>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-profile-form]');
        if (!form) return;
        const input = form.querySelector('[data-photo-input]');
        const preview = form.querySelector('[data-photo-preview]');
        input?.addEventListener('change', function () {
            const file = input.files && input.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.addEventListener('load', function () {
                preview.innerHTML = '';
                const image = document.createElement('img');
                image.src = String(reader.result);
                image.alt = @json(__('store::ui.store_auto_e15efff282'));
                preview.appendChild(image);
            });
            reader.readAsDataURL(file);
        });
    });
    </script>
</x-frontend-layout>
