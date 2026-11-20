@push('styles')
    <link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
@endpush

<x-app-layout>
    <main class="z4-exhibitions-admin" data-ve-halls-index>
        <div class="z4-exhibitions-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_3abf194d6c') }}</span>
                    <h1>{{ __('exhibitions::ui.auto_6ec6f6c888') }}</h1>
                    <p>{{ __('exhibitions::ui.auto_b1e6152202') }}</p>
                </div>
                @can('exhibitions.virtual_rooms.manage')
                    <a class="z4-exhibitions-button" href="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.index', ['new' => 1]) }}">{{ __('exhibitions::ui.auto_d9a245804b') }}</a>
                @endcan
            </header>

            @if(session('status'))
                <div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>
            @endif

            <section class="z4-exhibitions-panel" data-halls-list>
                <div class="z4-ve-version-list">
                    @forelse($versions as $version)
                        <article class="z4-ve-room-row">
                            <div>
                                <span class="z4-exhibitions-status is-{{ $version->status === 'published' ? 'ready' : 'pending' }}">{{ ucfirst($version->status) }}</span>
                                <h2>{{ $version->environment_title }} &middot; v{{ $version->version_number }}</h2>
                                <p><code>{{ $version->runtime_sha256 }}</code></p>
                            </div>
                            <dl>
                                <div><dt>{{ __('exhibitions::ui.auto_bc366f2d0b') }}</dt><dd>{{ number_format($version->runtime_size) }} bytes &middot; {{ data_get($version->stats, 'runtime.ktx2_textures', 0) }} KTX2 textures</dd></div>
                                <div><dt>{{ __('exhibitions::ui.auto_15fb6ccbd1') }}</dt><dd>{{ number_format($version->collision_size) }} bytes &middot; {{ number_format(data_get($version->stats, 'collision.triangles', 0)) }} triangles</dd></div>
                                <div><dt>{{ __('exhibitions::ui.auto_aaf7099b01') }}</dt><dd>{{ $version->room_count }} rooms &middot; {{ $version->slot_count }} slots</dd></div>
                                <div><dt>{{ __('exhibitions::ui.auto_13148717f8') }}</dt><dd>{{ data_get($version->validation, 'runtime.numErrors', 1) }} runtime errors &middot; {{ data_get($version->validation, 'collision.numErrors', 1) }} collision errors</dd></div>
                            </dl>
                            <div class="z4-ve-actions">
                                @if(in_array($version->status, ['ready', 'published', 'superseded'], true))
                                    <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.preview', [$version->public_id, 'return' => 'halls']) }}" target="_blank" rel="noopener">{{ __('exhibitions::ui.auto_30746d651c') }}</a>
                                @endif
                                @can('exhibitions.virtual_rooms.manage')
                                    <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.edit', $version->public_id) }}">{{ __('exhibitions::ui.auto_603070daa3') }}</a>
                                @endcan
                                @if($version->status !== 'published')
                                    @can('exhibitions.virtual_rooms.publish')
                                        <form method="post" action="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.publish', $version->public_id) }}">
                                            @csrf
                                            <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_74879bc966') }}</button>
                                        </form>
                                    @endcan
                                @endif
                                @can('exhibitions.virtual_rooms.manage')
                                    <form method="post" action="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.duplicate', $version->public_id) }}">
                                        @csrf
                                        <button class="z4-exhibitions-button is-secondary" type="submit">{{ __('exhibitions::ui.auto_81fc70bc58') }}</button>
                                    </form>
                                @endcan
                                @can('exhibitions.virtual_rooms.publish')
                                    <form method="post" action="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.destroy', $version->public_id) }}" onsubmit="return confirm('Delete this hall version? This cannot be undone.');">
                                        @csrf
                                        @method('delete')
                                        <button class="z4-exhibitions-button is-danger" type="submit">{{ __('exhibitions::ui.auto_6e82ba8676') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </article>
                    @empty
                        <div class="z4-exhibitions-empty">
                            <strong>{{ __('exhibitions::ui.auto_38c2764e13') }}</strong>
                            <p>{{ __('exhibitions::ui.auto_2b140b5253') }}</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </main>
</x-app-layout>
