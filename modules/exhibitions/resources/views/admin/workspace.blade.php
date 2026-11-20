@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ config('exhibitions.version') }}-workspace-12" data-exhibitions-stylesheet>
@endpush

<x-app-layout>
<main class="z4-exhibitions-admin" data-admin-workspace="{{ $area }}">
<div class="z4-exhibitions-container">
    <header class="z4-exhibitions-heading">
        <div>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_f2e8aff19d') }}</span>
            <h1>{{ $definition['label'] }}</h1>
            <p>{{ $area === 'artworks' ? __('exhibitions::ui.workspace_artworks_subtitle') : __('exhibitions::ui.workspace_artists_subtitle') }}</p>
        </div>
        <div class="z4-workspace-heading-actions">
            @if($area === 'artists' && auth()->user()->can('exhibitions.manage'))
                <a class="z4-exhibitions-button" href="{{ route('admin.plugins.exhibitions.artists.create') }}">{{ __('exhibitions::ui.auto_b7f8fee51b') }}</a>
            @elseif($area === 'artworks' && auth()->user()->can('exhibitions.manage'))
                <a class="z4-exhibitions-button" href="{{ route('admin.plugins.exhibitions.artworks.create') }}">{{ __('exhibitions::ui.auto_354474fbc8') }}</a>
                <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.artworks.import') }}">{{ __('exhibitions::ui.auto_28ec2d08ef') }}</a>
                <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.artworks.export') }}">{{ __('exhibitions::ui.auto_c04f1e4841') }}</a>
            @endif
            <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.overview') }}">{{ __('exhibitions::ui.auto_2938c7f7e5') }}</a>
        </div>
    </header>

    @if(session('status'))<div class="z4-exhibitions-alert is-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->has('artwork_delete'))<div class="z4-exhibitions-alert is-error" role="alert">{{ $errors->first('artwork_delete') }}</div>@endif
    @if($errors->has('artist'))<div class="z4-exhibitions-alert is-error" role="alert">{{ $errors->first('artist') }}</div>@endif

    <nav class="z4-admin-tabs" aria-label="{{ __('exhibitions::ui.auto_c856785f4d') }}">
        @foreach($definition['tabs'] as $key => $label)
            <a @class(['is-active' => $tab === $key]) @if($tab === $key) aria-current="page" @endif href="{{ route('admin.plugins.exhibitions.workspace', ['area' => $area, 'tab' => $key]) }}"><span>{{ $label }}</span><b>{{ number_format($counts[$key]) }}</b></a>
        @endforeach
    </nav>

    <form class="z4-exhibitions-filters" method="get">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label>{{ __('exhibitions::ui.auto_13348442cc') }}<input name="search" value="{{ $search }}" placeholder="{{ $area === 'artworks' ? __('exhibitions::ui.workspace_search_artworks') : __('exhibitions::ui.workspace_search_artists') }}"></label>
        <label>{{ __('exhibitions::ui.auto_6b46ae4842') }}<select name="sort"><option value="updated_at" @selected($sort === 'updated_at')>{{ __('exhibitions::ui.auto_ffbb333118') }}</option><option value="title" @selected($sort === 'title')>{{ __('exhibitions::ui.auto_b78a322350') }}</option><option value="state" @selected($sort === 'state')>{{ __('exhibitions::ui.auto_46a2a41cc6') }}</option></select></label>
        <label>{{ __('exhibitions::ui.auto_02674a4ef3') }}<select name="direction"><option value="desc" @selected($direction === 'desc')>{{ __('exhibitions::ui.auto_e3cf5ac194') }}</option><option value="asc" @selected($direction === 'asc')>{{ __('exhibitions::ui.auto_cf3fb1ff52') }}</option></select></label>
        <button>{{ __('exhibitions::ui.auto_9639e32cab') }}</button>
    </form>

    <section class="z4-exhibitions-panel">
        <div class="z4-exhibitions-table-wrap">
            <table class="z4-exhibitions-table">
                <thead><tr>
                    <th>{{ $area === 'artworks' ? __('exhibitions::ui.artwork') : __('exhibitions::ui.record') }}</th>
                    @if($area === 'artworks')<th>{{ __('exhibitions::ui.auto_83b5a96ccd') }}</th>@endif
                    <th>{{ __('exhibitions::ui.auto_ec53a8c4f0') }}</th>
                    <th>{{ $area === 'artworks' ? __('exhibitions::ui.category') : __('exhibitions::ui.context') }}</th>
                    <th>{{ __('exhibitions::ui.auto_ff0a3b7f3d') }}</th>
                    @if($area !== 'artworks')<th>{{ __('exhibitions::ui.auto_29ee5d1ebc') }}</th>@endif
                    @if(in_array($area, ['artists', 'artworks'], true))<th>{{ $area === 'artworks' ? __('exhibitions::ui.actions') : __('exhibitions::ui.action') }}</th>@endif
                </tr></thead>
                <tbody>
                @forelse($rows as $row)
                    @php($artistAccountState = $area === 'artists' ? \Modules\Exhibition\Domain\Artists\ArtistAccountState::tryFrom((string) $row->state) : null)
                    @php($canManageArtistStatus = $artistAccountState !== null)
                    @php($canReviewArtwork = $area === 'artworks' && in_array((string) $row->state, ['submitted', 'under_review', 'changes_required'], true))
                    @php($hasPublicPage = $area === 'artworks' && $row->state === 'published')
                    @php($artworkPermalink = $area === 'artworks' ? (filled($row->slug ?? null) ? $row->slug : (Illuminate\Support\Str::slug($row->title) ?: $row->public_id)) : null)
                    @php($artworkPrimaryUrl = $area === 'artworks' ? ($hasPublicPage ? route('exhibitions.artworks.show', $artworkPermalink) : route('admin.plugins.exhibitions.artworks.view', $row->public_id)) : null)
                    <tr>
                        <td data-label="{{ $area === 'artworks' ? __('exhibitions::ui.artwork') : __('exhibitions::ui.record') }}"><strong>@if($area === 'artworks')<a class="z4-workspace-record-link" href="{{ $artworkPrimaryUrl }}" @if($hasPublicPage) target="_blank" rel="noopener" title="{{ __('exhibitions::ui.auto_d38a8d3f8f') }}" @endif>{{ $row->title }}</a>@elseif($canReviewArtwork)<a class="z4-workspace-record-link" href="{{ route('admin.plugins.exhibitions.artworks.review.show', $row->public_id) }}">{{ $row->title }}</a>@else{{ $row->title }}@endif</strong></td>
                        @if($area === 'artworks')<td data-label="{{ __('exhibitions::ui.artist_owner') }}" class="z4-workspace-owner"><strong>{{ $row->owner_name }}</strong></td>@endif
                        <td data-label="{{ __('exhibitions::ui.auto_ec53a8c4f0') }}"><span class="z4-exhibitions-pill">{{ $artistAccountState ? $artistAccountState->label() : __('exhibitions::ui.state_'.$row->state) }}</span></td>
                        <td data-label="{{ $area === 'artworks' ? __('exhibitions::ui.category') : __('exhibitions::ui.context') }}" class="z4-workspace-context">{{ $row->secondary ? __(trim($row->secondary)) : '—' }}</td>
                        <td data-label="{{ __('exhibitions::ui.updated') }}"><time datetime="{{ $row->updated_at }}"><span>{{ \Illuminate\Support\Carbon::parse($row->updated_at)->format('Y-m-d') }}</span><small>{{ \Illuminate\Support\Carbon::parse($row->updated_at)->format('H:i:s') }}</small></time></td>
                        @if($area !== 'artworks')<td data-label="{{ __('exhibitions::ui.identifier') }}"><code class="z4-workspace-identifier" title="{{ $row->public_id }}">{{ $row->public_id }}</code></td>@endif
                        @if($area === 'artworks')
                            <td data-label="{{ __('exhibitions::ui.action') }}" class="z4-workspace-row-action">
                                <details class="z4-artwork-action-menu" data-artwork-actions name="artwork-actions">
                                    <summary title="{{ __('exhibitions::ui.auto_569816f84b') }}" aria-label="{{ __('exhibitions::ui.auto_de8179e07f') }}">
                                        <svg aria-hidden="true" viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                                        <span class="z4-visually-hidden">{{ __('exhibitions::ui.auto_44be3e0c0a') }}</span>
                                    </summary>
                                    <nav class="z4-artwork-action-menu-panel" aria-label="{{ __('exhibitions::ui.auto_9c1ad358ee') }}">
                                        @if($hasPublicPage)
                                            <a href="{{ route('exhibitions.artworks.show', $artworkPermalink) }}" target="_blank" rel="noopener">
                                                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.75"/></svg>
                                                <span><strong>{{ __('exhibitions::ui.auto_d38a8d3f8f') }}</strong><small>{{ __('exhibitions::ui.auto_406599231d') }}</small></span>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.plugins.exhibitions.artworks.view', $row->public_id) }}">
                                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M5 3.5h10l4 4V20.5H5z"/><path d="M15 3.5v4h4M8 12h8M8 16h6"/></svg>
                                            <span><strong>{{ __('exhibitions::ui.auto_3b2c97ed7b') }}</strong><small>{{ __('exhibitions::ui.auto_74c4713128') }}</small></span>
                                        </a>
                                        @if(in_array((string) $row->state, ['draft','submitted','changes_required','rejected'], true) && auth()->user()->can('exhibitions.manage'))
                                            <a href="{{ route('admin.plugins.exhibitions.artworks.edit', $row->public_id) }}">
                                                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m4 20 4.5-1 10-10-3.5-3.5-10 10zM13.5 7l3.5 3.5"/></svg>
                                                <span><strong>{{ __('exhibitions::ui.auto_f4dea15970') }}</strong><small>{{ __('exhibitions::ui.auto_b367823b62') }}</small></span>
                                            </a>
                                        @endif
                                        @if($canReviewArtwork)
                                            <a href="{{ route('admin.plugins.exhibitions.artworks.review.show', $row->public_id) }}">
                                                <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="m8 12 2.3 2.3L16.5 8"/></svg>
                                                <span><strong>{{ __('exhibitions::ui.auto_623f5d888d') }}</strong><small>{{ __('exhibitions::ui.auto_4b3ba261c7') }}</small></span>
                                            </a>
                                        @endif
                                        @can('exhibitions.manage')
                                            <form method="post" action="{{ route('admin.plugins.exhibitions.artworks.destroy', $row->public_id) }}" data-delete-artwork data-artwork-title="{{ $row->title }}">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="confirmation" value="delete-artwork">
                                                <button type="submit" class="is-danger">
                                                    <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
                                                    <span><strong>{{ __('exhibitions::ui.auto_23a7081942') }}</strong><small>{{ __('exhibitions::ui.auto_7b08102820') }}</small></span>
                                                </button>
                                            </form>
                                        @endcan
                                    </nav>
                                </details>
                            </td>
                        @endif
                    </tr>
                    @if($area === 'artists')
                        <tr class="z4-workspace-actions-row"><td colspan="6" class="z4-workspace-row-action z4-workspace-actions-cell">@include('exhibitions::admin.partials.artist-actions', ['row' => $row, 'canManageArtistStatus' => $canManageArtistStatus])</td></tr>
                    @endif
                @empty
                    <tr class="z4-workspace-empty-row"><td colspan="{{ in_array($area, ['artists', 'artworks'], true) ? 6 : 5 }}" class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_b2009b86c5') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <x-platform-pagination :paginator="$rows" />
    </section>
</div>
</main>
<dialog class="z4-artwork-delete-dialog" data-artwork-delete-dialog aria-labelledby="z4-artwork-delete-title" aria-describedby="z4-artwork-delete-description">
    <div class="z4-artwork-delete-dialog__accent" aria-hidden="true"></div>
    <div class="z4-artwork-delete-dialog__body">
        <span class="z4-artwork-delete-dialog__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg>
        </span>
        <div>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_594ad44dc3') }}</span>
            <h2 id="z4-artwork-delete-title">{{ __('exhibitions::ui.auto_fd35759040') }}</h2>
            <p id="z4-artwork-delete-description">{{ __('exhibitions::ui.auto_9281b79df3') }} <strong data-artwork-delete-name></strong>{{ __('exhibitions::ui.auto_09403ebb31') }}</p>
            <p class="z4-artwork-delete-dialog__safety">{{ __('exhibitions::ui.auto_174a438160') }}</p>
        </div>
    </div>
    <div class="z4-artwork-delete-dialog__actions">
        <button type="button" class="z4-exhibitions-button is-secondary" data-artwork-delete-cancel>{{ __('exhibitions::ui.auto_ea4788705e') }}</button>
        <button type="button" class="z4-exhibitions-button is-danger" data-artwork-delete-accept>{{ __('exhibitions::ui.auto_23a7081942') }}</button>
    </div>
</dialog>
<dialog class="z4-artwork-delete-dialog" data-artist-delete-dialog aria-labelledby="z4-artist-delete-title" aria-describedby="z4-artist-delete-description">
    <div class="z4-artwork-delete-dialog__accent" aria-hidden="true"></div>
    <div class="z4-artwork-delete-dialog__body">
        <span class="z4-artwork-delete-dialog__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM2 21v-2a6 6 0 0 1 10.5-4M16 16l5 5M21 16l-5 5"/></svg>
        </span>
        <div>
            <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_d10ddf5696') }}</span>
            <h2 id="z4-artist-delete-title">{{ __('exhibitions::ui.auto_6c28d35ff0') }}</h2>
            <p id="z4-artist-delete-description">{{ __('exhibitions::ui.auto_8435323eb8') }} <strong data-artist-delete-name></strong> {{ __('exhibitions::ui.auto_c9fc18f2bb') }}</p>
            <p class="z4-artwork-delete-dialog__safety">{{ __('exhibitions::ui.auto_32ed42a14f') }}</p>
        </div>
    </div>
    <div class="z4-artwork-delete-dialog__actions">
        <button type="button" class="z4-exhibitions-button is-secondary" data-artist-delete-cancel>{{ __('exhibitions::ui.auto_ea4788705e') }}</button>
        <button type="button" class="z4-exhibitions-button is-danger" data-artist-delete-accept>{{ __('exhibitions::ui.auto_3506e68c66') }}</button>
    </div>
</dialog>
<script>
document.addEventListener('click', function (event) {
    document.querySelectorAll('[data-artwork-actions][open]').forEach(function (menu) {
        if (!menu.contains(event.target)) menu.removeAttribute('open');
    });
});
document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[data-artwork-actions][open]').forEach(function (menu) {
        menu.removeAttribute('open');
        menu.querySelector('summary')?.focus();
    });
});
document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-delete-artwork]');
    if (!form) return;
    if (form.dataset.deleteConfirmed === 'true') {
        delete form.dataset.deleteConfirmed;
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    var dialog = document.querySelector('[data-artwork-delete-dialog]');
    if (!dialog) return;
    dialog.pendingDeleteForm = form;
    dialog.querySelector('[data-artwork-delete-name]').textContent = '“' + (form.dataset.artworkTitle || 'this artwork') + '”';
    form.closest('details')?.removeAttribute('open');
    dialog.showModal();
    window.requestAnimationFrame(function () {
        dialog.querySelector('[data-artwork-delete-cancel]').focus();
    });
}, true);
document.querySelector('[data-artwork-delete-cancel]')?.addEventListener('click', function () {
    this.closest('dialog').close('cancel');
});
document.querySelector('[data-artwork-delete-accept]')?.addEventListener('click', function () {
    var dialog = this.closest('dialog');
    var form = dialog.pendingDeleteForm;
    if (!form) return;
    this.disabled = true;
    form.dataset.deleteConfirmed = 'true';
    dialog.close('confirm');
    form.requestSubmit();
});
document.querySelector('[data-artwork-delete-dialog]')?.addEventListener('click', function (event) {
    if (event.target === this) this.close('cancel');
});
document.querySelector('[data-artwork-delete-dialog]')?.addEventListener('close', function () {
    this.pendingDeleteForm = null;
    var accept = this.querySelector('[data-artwork-delete-accept]');
    if (accept) accept.disabled = false;
});
document.addEventListener('submit', function (event) {
    var form = event.target.closest('[data-remove-artist]');
    if (!form) return;
    if (form.dataset.removeConfirmed === 'true') {
        delete form.dataset.removeConfirmed;
        return;
    }
    event.preventDefault();
    event.stopImmediatePropagation();
    var dialog = document.querySelector('[data-artist-delete-dialog]');
    if (!dialog) return;
    dialog.pendingDeleteForm = form;
    dialog.querySelector('[data-artist-delete-name]').textContent = '“' + (form.dataset.artistName || 'this artist') + '”';
    dialog.showModal();
    window.requestAnimationFrame(function () {
        dialog.querySelector('[data-artist-delete-cancel]').focus();
    });
}, true);
document.querySelector('[data-artist-delete-cancel]')?.addEventListener('click', function () {
    this.closest('dialog').close('cancel');
});
document.querySelector('[data-artist-delete-accept]')?.addEventListener('click', function () {
    var dialog = this.closest('dialog');
    var form = dialog.pendingDeleteForm;
    if (!form) return;
    this.disabled = true;
    form.dataset.removeConfirmed = 'true';
    dialog.close('confirm');
    form.requestSubmit();
});
document.querySelector('[data-artist-delete-dialog]')?.addEventListener('click', function (event) {
    if (event.target === this) this.close('cancel');
});
document.querySelector('[data-artist-delete-dialog]')?.addEventListener('close', function () {
    this.pendingDeleteForm = null;
    var accept = this.querySelector('[data-artist-delete-accept]');
    if (accept) accept.disabled = false;
});
</script>
</x-app-layout>
