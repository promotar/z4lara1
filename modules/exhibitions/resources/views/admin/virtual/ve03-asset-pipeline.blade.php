@push('styles')
    <link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
@endpush

@php
    $stateLabels = [
        'complete' => __('exhibitions::ui.state_complete'),
        'current' => __('exhibitions::ui.state_current'),
        'pending' => __('exhibitions::ui.state_pending'),
    ];
@endphp

<x-app-layout>
    <main class="z4-exhibitions-admin" data-ve-room-pipeline>
        <div class="z4-exhibitions-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_3abf194d6c') }}</span>
                    <h1>{{ $replacementTarget ? __('exhibitions::ui.ve03_replace_title') : __('exhibitions::ui.ve03_add_title') }}</h1>
                    <p>{{ $replacementTarget ? __('exhibitions::ui.ve03_replace_copy', ['title' => $replacementTarget->environment_title]) : __('exhibitions::ui.ve03_add_copy') }}</p>
                </div>
                <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.virtual.rooms.index') }}">{{ __('exhibitions::ui.auto_af3b184745') }}</a>
            </header>

            @if(session('status'))
                <div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>
            @endif

            <ol class="z4-ve-pipeline-steps" aria-label="{{ __('exhibitions::ui.auto_912993caf8') }}">
                @foreach([
                    ['key' => 'upload', 'number' => '01', 'title' => __('exhibitions::ui.ve03_step_upload'), 'copy' => __('exhibitions::ui.ve03_step_upload_copy')],
                    ['key' => 'import', 'number' => '02', 'title' => __('exhibitions::ui.ve03_step_import'), 'copy' => __('exhibitions::ui.ve03_step_import_copy')],
                    ['key' => 'publish', 'number' => '03', 'title' => __('exhibitions::ui.ve03_step_publish'), 'copy' => __('exhibitions::ui.ve03_step_publish_copy')],
                ] as $step)
                    <li class="is-{{ $workflow[$step['key']] }}" data-pipeline-step="{{ $step['key'] }}">
                        <span>{{ $step['number'] }}</span>
                        <div>
                            <small>{{ $stateLabels[$workflow[$step['key']]] }}</small>
                            <h2>{{ $step['title'] }}</h2>
                            <p>{{ $step['copy'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if($workflow['upload'] === 'current')
                <section class="z4-exhibitions-panel z4-ve-pipeline-section" data-pipeline-upload id="new-hall">
                    <div class="z4-ve-pipeline-section__heading">
                        <span>{{ __('exhibitions::ui.auto_c8d606eabb') }}</span>
                        <div>
                            <h2>{{ __('exhibitions::ui.auto_77361ac848') }}</h2>
                            <p>{{ __('exhibitions::ui.auto_2c04f1690a') }}</p>
                        </div>
                    </div>

                    @can('exhibitions.virtual_rooms.manage')
                        <form class="z4-ve-form" method="post" enctype="multipart/form-data" action="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.upload') }}">
                            @csrf
                            @if($replacementTarget)
                                <input type="hidden" name="replaces" value="{{ $replacementTarget->public_id }}">
                            @endif
                            <label class="is-wide">{{ __('exhibitions::ui.auto_360444ce61') }}<input type="file" name="asset" accept=".glb,model/gltf-binary" required></label>
                            <label class="is-wide">{{ __('exhibitions::ui.auto_51b04060df') }}<input type="file" name="build_bundle" accept=".zip,application/zip" required></label>
                            <details class="z4-ve-pipeline-disclosure is-wide">
                                <summary>{{ __('exhibitions::ui.auto_b82cb0470c') }}</summary>
                                <p>{{ __('exhibitions::ui.auto_785cad803e') }}</p>
                                <ul class="z4-ve-build-files">
                                    @foreach($requiredBuildFiles as $file)
                                        <li><code>{{ $file }}</code></li>
                                    @endforeach
                                </ul>
                            </details>
                            <div class="is-wide"><button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_fc812e32e7') }}</button></div>
                        </form>
                    @else
                        <div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_ce180ecd4e') }}</strong><p>{{ __('exhibitions::ui.auto_f156cadd21') }}</p></div>
                    @endcan
                </section>
            @endif

            @if($workflow['import'] === 'current')
                <div class="z4-ve-pipeline-stack" data-pipeline-step-stack>
                    <section class="z4-exhibitions-panel z4-ve-pipeline-section" data-pipeline-import>
                    <div class="z4-ve-pipeline-section__heading">
                        <span>{{ __('exhibitions::ui.auto_6f9f0176eb') }}</span>
                        <div>
                            <h2>{{ __('exhibitions::ui.auto_f999cfbc74') }}</h2>
                            <p>{{ __('exhibitions::ui.auto_2f79b1418c') }}</p>
                        </div>
                    </div>

                        <ul class="z4-ve-pipeline-checklist">
                            <li>{{ __('exhibitions::ui.auto_738548ba25') }}</li>
                            <li>{{ __('exhibitions::ui.auto_0edf963785') }}</li>
                            <li>{{ __('exhibitions::ui.auto_531ce4f6ab') }}</li>
                            <li>{{ __('exhibitions::ui.auto_6c37e4a693') }}</li>
                        </ul>
                        @can('exhibitions.virtual_rooms.manage')
                            <form method="post" action="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.import') }}">
                                @csrf
                                <input type="hidden" name="source_hash" value="{{ $activeUpload->sha256 }}">
                                <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_ceec72e3f0') }}</button>
                            </form>
                        @endcan
                    </section>
                </div>
            @endif

            <section class="z4-exhibitions-panel z4-ve-pipeline-section" data-pipeline-quarantine>
                <div class="z4-ve-pipeline-section__heading">
                    <span>{{ __('exhibitions::ui.auto_986528f704') }}</span>
                    <div>
                        <h2>{{ __('exhibitions::ui.auto_3621311751') }}</h2>
                        <p>{{ __('exhibitions::ui.auto_511a438616') }}</p>
                    </div>
                </div>
                <div class="z4-ve-source-list">
                    @forelse($uploads as $upload)
                        <article>
                            <div>
                                <strong>{{ $upload->original_name }}</strong>
                                <span>{{ $upload->bytes > 0 ? number_format($upload->bytes).' bytes' : 'Size unavailable' }} &middot; {{ str_replace('_', ' ', $upload->status) }}</span>
                            </div>
                            <code title="{{ $upload->sha256 }}">{{ substr($upload->sha256, 0, 12) }}&hellip;</code>
                        </article>
                    @empty
                        <div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_a6d1240b11') }}</strong><p>{{ __('exhibitions::ui.auto_4defb07f79') }}</p></div>
                    @endforelse
                </div>
            </section>

            @if($workflow['publish'] === 'current' || $workflow['publish'] === 'complete')
                <section class="z4-exhibitions-panel z4-ve-pipeline-section" data-pipeline-versions>
                <div class="z4-ve-pipeline-section__heading">
                    <span>{{ __('exhibitions::ui.auto_fa213deb89') }}</span>
                    <div>
                        <h2>{{ __('exhibitions::ui.auto_955330e4e1') }}</h2>
                        <p>{{ __('exhibitions::ui.auto_e3557703ee') }}</p>
                    </div>
                </div>

                <div class="z4-ve-version-list">
                    @forelse($versions as $version)
                        <article class="z4-ve-room-row">
                            <div>
                                <span class="z4-exhibitions-status is-{{ $version->status === 'published' ? 'ready' : 'pending' }}">{{ ucfirst($version->status) }}</span>
                                <h3>{{ $version->environment_title }} &middot; v{{ $version->version_number }}</h3>
                                <p><code>{{ $version->runtime_sha256 }}</code></p>
                            </div>
                            <dl>
                                <div><dt>{{ __('exhibitions::ui.auto_bc366f2d0b') }}</dt><dd>{{ number_format($version->runtime_size) }} bytes &middot; {{ data_get($version->stats, 'runtime.ktx2_textures', 0) }} KTX2 textures</dd></div>
                                <div><dt>{{ __('exhibitions::ui.auto_15fb6ccbd1') }}</dt><dd>{{ number_format($version->collision_size) }} bytes &middot; {{ number_format(data_get($version->stats, 'collision.triangles', 0)) }} triangles</dd></div>
                                <div><dt>{{ __('exhibitions::ui.auto_aaf7099b01') }}</dt><dd>{{ $version->room_count }} rooms &middot; {{ $version->slot_count }} slots</dd></div>
                                <div><dt>{{ __('exhibitions::ui.auto_13148717f8') }}</dt><dd>{{ data_get($version->validation, 'runtime.numErrors', 1) }} runtime errors &middot; {{ data_get($version->validation, 'collision.numErrors', 1) }} collision errors</dd></div>
                            </dl>
                            <div class="z4-ve-actions">
                                <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.preview', $version->public_id) }}" target="_blank" rel="noopener">{{ __('exhibitions::ui.auto_7d0b61c6d3') }}</a>
                                @if($version->status !== 'published')
                                    <form method="post" action="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.publish', $version->public_id) }}">
                                        @csrf
                                        <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_18dd87d85e') }}</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_121d971e46') }}</strong><p>{{ __('exhibitions::ui.auto_4f21468eb5') }}</p></div>
                    @endforelse
                </div>
                </section>
            @endif
        </div>
    </main>
</x-app-layout>
