@push('styles')
    <link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>
@endpush

<x-app-layout>
    <main class="z4-exhibitions-admin" data-ve-layout-editor>
        <div class="z4-exhibitions-container z4-ve-layout-page">
            <header class="z4-exhibitions-heading">
                <div>
                    <span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_417fd0011b') }}</span>
                    <h1>{{ $room->title }}</h1>
                    <p>{{ __('exhibitions::ui.auto_0e11f78eb2') }}</p>
                </div>
                <a class="z4-ve-layout-heading-link" href="{{ route('admin.plugins.exhibitions.virtual.rooms.edit', $room->public_id) }}">{{ __('exhibitions::ui.auto_45c5b782f5') }}</a>
            </header>

            @if(session('status'))
                <div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>
            @endif

            @php($viewerPanoramaUrl = route('admin.plugins.exhibitions.virtual.rooms.panorama', $room->public_id))
            @php($viewerPlacements = $slots->map(fn($slot) => ['id' => $slot->public_id, 'name' => $slot->name, 'title' => $slot->code, 'yaw' => (float) $slot->anchor_yaw, 'pitch' => (float) $slot->anchor_pitch, 'roll' => (float) $slot->roll, 'width' => (float) $slot->angular_width, 'maximum_height' => $slot->maximum_angular_height ? (float) $slot->maximum_angular_height : null])->all())
            @php($viewerMode = 'layout')
            @include('exhibitions::virtual._viewer')

            <div class="z4-ve-layout-grid">
                <form class="z4-exhibitions-panel z4-ve-form z4-ve-create-slot" data-layout-slot-form method="post" action="{{ route('admin.plugins.exhibitions.virtual.slots.store', $room->public_id) }}">
                    @csrf
                    <header class="is-wide z4-ve-create-slot__header">
                        <div>
                            <span>{{ __('exhibitions::ui.auto_07a9b10321') }}</span>
                            <h2>{{ __('exhibitions::ui.auto_678987dd90') }}</h2>
                        </div>
                        <p>{{ __('exhibitions::ui.auto_fa1e8631eb') }}</p>
                    </header>
                    <label>{{ __('exhibitions::ui.auto_ca0dbad92a') }}<input name="code" required maxlength="64" placeholder="{{ __('exhibitions::ui.auto_d13bad9aa8') }}"></label>
                    <label>{{ __('exhibitions::ui.auto_326a2937cf') }}<input name="name" required maxlength="140" placeholder="{{ __('exhibitions::ui.auto_03fac4538f') }}"></label>
                    <label>{{ __('exhibitions::ui.auto_c5d6d1002e') }}<input type="number" step="0.001" name="anchor_yaw" required value="0"></label>
                    <label>{{ __('exhibitions::ui.auto_87f800274a') }}<input type="number" step="0.001" name="anchor_pitch" required value="0"></label>
                    <label>{{ __('exhibitions::ui.auto_25b5eb3bbe') }}<input type="number" step="0.001" name="roll" required value="0"></label>
                    <label>{{ __('exhibitions::ui.auto_934a1d0311') }}<input type="number" step="0.001" name="angular_width" required value="24"></label>
                    <label>{{ __('exhibitions::ui.auto_3030f84fca') }}<input type="number" step="0.001" name="maximum_angular_height"></label>
                    <label>{{ __('exhibitions::ui.auto_a240fa2792') }}<input type="number" name="display_order" required value="0"></label>
                    <label class="is-wide">{{ __('exhibitions::ui.auto_fe17bf0c07') }}</label>
                    <div class="z4-ve-slot-form-footer is-wide">
                        <label class="z4-ve-checkbox"><input type="checkbox" name="enabled" value="1" checked> {{ __('exhibitions::ui.auto_00d23a76e4') }}</label>
                        <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_a52e076132') }}</button>
                    </div>
                </form>

                <section class="z4-ve-slot-stack" aria-label="{{ __('exhibitions::ui.auto_bfae30cfa8') }}">
                    @forelse($slots as $slot)
                        <article class="z4-exhibitions-panel z4-ve-slot-card">
                            <form class="z4-ve-form z4-ve-slot-edit" method="post" action="{{ route('admin.plugins.exhibitions.virtual.slots.update', $slot->public_id) }}">
                                @csrf
                                @method('put')
                                <header class="is-wide z4-ve-slot-card__header">
                                    <div>
                                        <span>{{ $slot->code }}</span>
                                        <h2>{{ $slot->name }}</h2>
                                    </div>
                                    <strong class="z4-ve-slot-state {{ $slot->active_request_id ? 'is-reserved' : ($slot->enabled ? 'is-available' : 'is-disabled') }}">
                                        {{ $slot->active_request_id ? 'Reserved / occupied' : ($slot->enabled ? 'Available' : 'Disabled') }}
                                    </strong>
                                </header>

                                @foreach(['code', 'name', 'anchor_yaw', 'anchor_pitch', 'roll', 'angular_width', 'maximum_angular_height', 'display_order'] as $field)
                                    <label>
                                        {{ __('exhibitions::ui.state_'.$field) }}
                                        <input name="{{ $field }}" value="{{ $slot->$field }}" @if(!in_array($field, ['code', 'name'])) type="number" step="0.001" @endif @if($field !== 'maximum_angular_height') required @endif>
                                    </label>
                                @endforeach

                                <label class="is-wide">{{ __('exhibitions::ui.auto_54d5a706a4') }}</label>
                                <div class="z4-ve-slot-form-footer is-wide">
                                    <label class="z4-ve-checkbox"><input type="checkbox" name="enabled" value="1" @checked($slot->enabled)> {{ __('exhibitions::ui.auto_00d23a76e4') }}</label>
                                    <button class="z4-exhibitions-button" type="submit">{{ __('exhibitions::ui.auto_daa6b483e9') }}</button>
                                </div>
                            </form>

                            <form class="z4-ve-remove-form" method="post" action="{{ route('admin.plugins.exhibitions.virtual.slots.destroy', $slot->public_id) }}" onsubmit="return confirm('Remove this unoccupied slot? Historical occupied slots are disabled instead.');">
                                @csrf
                                @method('delete')
                                <button class="z4-exhibitions-button is-danger" type="submit">{{ __('exhibitions::ui.auto_29c2891072') }}</button>
                            </form>
                        </article>
                    @empty
                        <div class="z4-exhibitions-empty">{{ __('exhibitions::ui.auto_5a02c53c61') }}</div>
                    @endforelse
                </section>
            </div>
        </div>
    </main>
</x-app-layout>

<script src="{{ route('exhibitions.assets.virtual-exhibition-script') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/js/virtual-exhibition.js')) }}"></script>
