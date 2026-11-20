@push('styles')
    <link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
@endpush

<x-app-layout>
    <main class="z4-exhibitions-admin" data-ve-hall-edit>
        <div class="z4-exhibitions-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_3abf194d6c') }}</span>
                    <h1>{{ __('exhibitions::ui.auto_765ac31154') }}</h1>
                    <p>{{ __('exhibitions::ui.auto_e0c3997dc7') }}</p>
                </div>
                <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.virtual.rooms.index') }}">{{ __('exhibitions::ui.auto_af3b184745') }}</a>
            </header>

            @if(session('status'))
                <div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>
            @endif

            <div class="z4-ve-pipeline-stack">
                <section class="z4-exhibitions-panel z4-ve-pipeline-section">
                    <div class="z4-ve-pipeline-section__heading">
                        <span>{{ __('exhibitions::ui.auto_3ec365dd53') }}</span>
                        <div>
                            <h2>{{ __('exhibitions::ui.auto_61eebe08de') }}</h2>
                            <p>{{ __('exhibitions::ui.auto_55c80b63c4') }}</p>
                        </div>
                    </div>

                    <form class="z4-ve-form" method="post" action="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.update', $version->public_id) }}">
                        @csrf
                        @method('put')
                        <label class="is-wide">{{ __('exhibitions::ui.auto_f8944febe6') }}
                            <input type="text" name="title" maxlength="180" value="{{ old('title', $version->environment_title) }}" required>
                        </label>
                        @foreach($rooms as $room)
                            <label>{{ __('exhibitions::ui.auto_abbca281e1') }}
                                <input type="text" name="room_titles[{{ $room->room_code }}]" maxlength="180" value="{{ old('room_titles.'.$room->room_code, $room->title) }}" required>
                            </label>
                        @endforeach
                        <div class="is-wide">
                            <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_f719ca8a87') }}</button>
                        </div>
                    </form>
                </section>

                <section class="z4-exhibitions-panel z4-ve-pipeline-section">
                    <div class="z4-ve-pipeline-section__heading">
                        <span>{{ __('exhibitions::ui.auto_4c4344644b') }}</span>
                        <div>
                            <h2>{{ __('exhibitions::ui.auto_53fb063792') }}</h2>
                            <p>{{ __('exhibitions::ui.auto_9cfcdc29ee') }}</p>
                        </div>
                    </div>
                    <div class="z4-ve-actions">
                        <a class="z4-exhibitions-button is-secondary" href="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.preview', [$version->public_id, 'return' => 'edit']) }}" target="_blank" rel="noopener">{{ __('exhibitions::ui.auto_30746d651c') }}</a>
                        <a class="z4-exhibitions-button" href="{{ route('admin.plugins.exhibitions.virtual.ve03.assets.index', ['new' => 1, 'replaces' => $version->public_id]) }}">{{ __('exhibitions::ui.auto_b17a98000d') }}</a>
                    </div>
                </section>
            </div>
        </div>
    </main>
</x-app-layout>
