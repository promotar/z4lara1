@push('styles')
<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
@endpush

<x-app-layout>
    <main class="z4-exhibitions-admin">
        <div class="z4-exhibitions-container">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_98e93b2a1e') }}</span>
                    <h1>{{ $record->artwork_title }}</h1>
                    <p>{{ $record->artistic_name ?: $record->legal_name }} &middot; {{ $record->room_title }} &middot; {{ $record->slot_name }}</p>
                </div>
                <span class="z4-exhibitions-status">{{ __('exhibitions::ui.state_'.$record->status) }}</span>
            </header>

            @if(session('status'))
                <div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>
            @endif

            @php
                $snapshot = json_decode($record->artwork_snapshot, true) ?: [];
                $viewerPanoramaUrl = route('admin.plugins.exhibitions.virtual.rooms.panorama', $record->room_public_id);
                $viewerPlacements = [array_merge($snapshot, [
                    'id' => $record->public_id,
                    'yaw' => (float) $record->placement_yaw,
                    'pitch' => (float) $record->placement_pitch,
                    'roll' => (float) $record->placement_roll,
                    'width' => (float) $record->placement_width,
                    'maximum_height' => $record->maximum_height,
                    'media_url' => isset($snapshot['media_public_id'])
                        ? route('exhibitions.artworks.media', [$record->artwork_public_id, $snapshot['media_public_id']])
                        : null,
                    'availability' => 'Review',
                ])];
                $room = (object) [
                    'initial_yaw' => $record->placement_yaw,
                    'initial_pitch' => $record->placement_pitch,
                    'initial_fov' => 75,
                    'minimum_fov' => 35,
                    'maximum_fov' => 110,
                    'auto_rotate_enabled' => false,
                    'auto_rotate_speed' => 0,
                    'auto_rotate_delay' => 0,
                ];
            @endphp

            @include('exhibitions::virtual._viewer')

            <div class="z4-ve-review-grid">
                <form class="z4-exhibitions-panel z4-ve-form" method="post" action="{{ route('admin.plugins.exhibitions.virtual.requests.decide', $record->public_id) }}">
                    @csrf
                    <h2>{{ __('exhibitions::ui.auto_7c66686983') }}</h2>
                    <label>
                        {{ __('exhibitions::ui.auto_5d30f54fc5') }}
                        <select name="decision" required>
                            @foreach(in_array($record->status, ['approved', 'published']) ? ['removed'] : ['changes_requested', 'approved', 'rejected'] as $decision)
                                <option value="{{ $decision }}">{{ __('exhibitions::ui.state_'.$decision) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        {{ __('exhibitions::ui.auto_fecbe03d5a') }}
                        <select name="slot_public_id">
                            @foreach($slots as $slot)
                                <option value="{{ $slot->public_id }}" @selected($slot->id === $record->slot_id)>{{ $slot->code }} &middot; {{ $slot->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @foreach(['placement_yaw', 'placement_pitch', 'placement_roll', 'placement_width', 'maximum_height'] as $field)
                        <label>
                            {{ __('exhibitions::ui.state_'.$field) }}
                            <input type="number" step="0.001" name="{{ $field }}" value="{{ $record->$field }}">
                        </label>
                    @endforeach
                    <label class="is-wide">
                        {{ __('exhibitions::ui.auto_2e0f5c2962') }}
                    </label>
                    <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_88831d97bb') }}</button>
                </form>

                <section class="z4-exhibitions-panel">
                    <h2>{{ __('exhibitions::ui.auto_cce81a8b31') }}</h2>
                    <ol class="z4-ve-timeline">
                        @foreach($history as $event)
                            <li>
                                <strong>{{ __('exhibitions::ui.state_'.$event->to_status) }}</strong>
                                <span>{{ $event->occurred_at }}</span>
                                <p>{{ $event->reason }}</p>
                            </li>
                        @endforeach
                    </ol>
                </section>
            </div>
        </div>
    </main>
</x-app-layout>

<script src="{{ route('exhibitions.assets.virtual-exhibition-script') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/js/virtual-exhibition.js')) }}"></script>
