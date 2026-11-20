@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-artist-admin" data-exhibitions-stylesheet>
@endpush
<x-app-layout>
<main class="z4-exhibitions-admin">
<div class="z4-exhibitions-container z4-exhibitions-form-container">
    <header class="z4-exhibitions-heading">
        <div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_5fdad04dfb') }}</span><h1>{{ __('exhibitions::ui.auto_7b54b1504c') }}</h1><p>{{ __('exhibitions::ui.auto_1ac22ebb4c') }}</p></div>
        <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.workspace',['area'=>'artists','tab'=>'active']) }}">{{ __('exhibitions::ui.auto_b37af52dee') }}</a>
    </header>
    @if($errors->any())<div class="z4-exhibitions-alert is-error" role="alert">{{ $errors->first() }}</div>@endif
    <form class="z4-exhibitions-filters" method="get">
        <label>{{ __('exhibitions::ui.auto_449b341175') }}<input name="search" value="{{ $search }}" placeholder="{{ __('exhibitions::ui.auto_28a2cf5f8c') }}"></label>
        <button type="submit">{{ __('exhibitions::ui.auto_13348442cc') }}</button>
    </form>
    <section class="z4-exhibitions-panel">
        <div class="z4-exhibitions-table-wrap">
            <table class="z4-exhibitions-table">
                <thead><tr><th>{{ __('exhibitions::ui.auto_8f9bfe9d13') }}</th><th>{{ __('exhibitions::ui.auto_123710059f') }}</th><th>{{ __('exhibitions::ui.auto_b513c5eaa1') }}</th><th>{{ __('exhibitions::ui.auto_004bf6c9a4') }}</th></tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td data-label="{{ __('exhibitions::ui.data_user') }}"><strong>{{ $user->name }}</strong></td>
                        <td data-label="{{ __('exhibitions::ui.data_email_status') }}"><span class="z4-exhibitions-pill">{{ $user->email_verified_at ? __('exhibitions::ui.verified') : __('exhibitions::ui.not_verified') }}</span><small class="z4-admin-row-note">{{ $user->email }}</small></td>
                        <td colspan="2" class="z4-inline-admin-form">
                            <form method="post" action="{{ route('admin.plugins.exhibitions.artists.store') }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                                <label class="z4-visually-hidden" for="artist-name-{{ $user->id }}">{{ __('exhibitions::ui.auto_b513c5eaa1') }}</label>
                                <input id="artist-name-{{ $user->id }}" name="artistic_name" maxlength="160" value="{{ $user->name }}">
                                <button type="submit" class="z4-exhibitions-button">{{ __('exhibitions::ui.auto_7f0d771a6d') }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_d8523d4b49') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-platform-pagination :paginator="$users" />
    </section>
</div>
</main>
</x-app-layout>
