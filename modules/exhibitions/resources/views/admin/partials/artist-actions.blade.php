<div class="z4-artist-row-actions">
    @if((string) $row->state === 'active' && auth()->user()->can('exhibitions.finance'))
        <a href="{{ route('admin.plugins.exhibitions.artists.financial-terms.show', $row->public_id) }}">{{ __('exhibitions::ui.auto_2bdb0c85ae') }}</a>
    @else
        <a href="{{ route('admin.plugins.exhibitions.applications.show', $row->public_id) }}{{ $canManageArtistStatus ? '#artist-account-actions' : '' }}">{{ $canManageArtistStatus ? 'Manage status' : 'Review application' }}</a>
    @endif
    @if(auth()->user()->can('exhibitions.manage') && filled($row->artist_public_id ?? null))
        @if((string) $row->state === 'active')
            <form method="post" action="{{ route('admin.plugins.exhibitions.artists.deactivate', $row->artist_public_id) }}">@csrf<button type="submit" class="is-warning">{{ __('exhibitions::ui.auto_bcfaccebf7') }}</button></form>
        @elseif(in_array((string) $row->state, ['restricted','suspended','terminated'], true))
            <form method="post" action="{{ route('admin.plugins.exhibitions.artists.activate', $row->artist_public_id) }}">@csrf<button type="submit">{{ __('exhibitions::ui.auto_a13367a8e2') }}</button></form>
        @endif
        @if((string) $row->state === 'active')
            <form method="post" action="{{ route('admin.plugins.exhibitions.artists.auto-approval', $row->artist_public_id) }}">@csrf<input type="hidden" name="enabled" value="{{ (int) ($row->auto_approve_artworks ?? 0) === 1 ? 0 : 1 }}"><button type="submit" style="white-space:nowrap;font-size:11.5px;padding:6px 9px" title="{{ (int) ($row->auto_approve_artworks ?? 0) === 1 ? __('exhibitions::ui.auto_on_help') : __('exhibitions::ui.auto_off_help') }}" class="{{ (int) ($row->auto_approve_artworks ?? 0) === 1 ? 'is-warning' : '' }}">{{ (int) ($row->auto_approve_artworks ?? 0) === 1 ? __('exhibitions::ui.auto_on') : __('exhibitions::ui.auto_approve') }}</button></form>
        @endif
        @if((string) $row->state !== 'terminated')
            <form method="post" action="{{ route('admin.plugins.exhibitions.artists.destroy', $row->artist_public_id) }}" data-remove-artist data-artist-name="{{ $row->title }}">@csrf @method('DELETE')<input type="hidden" name="confirmation" value="remove-artist"><button type="submit" class="is-danger">{{ __('exhibitions::ui.auto_f2a6c498fb') }}</button></form>
        @endif
    @endif
</div>
