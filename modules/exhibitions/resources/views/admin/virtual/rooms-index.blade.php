@push('styles')<link rel="stylesheet" href="{{ route('admin.plugins.exhibitions.assets.stylesheet') }}?v={{ filemtime(base_path('modules/exhibitions/resources/private-assets/css/exhibitions.css')) }}" data-exhibitions-stylesheet>@endpush
<x-app-layout>
<main class="z4-exhibitions-admin"><div class="z4-exhibitions-container">
<header class="z4-exhibitions-heading"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_e749f205f8') }}</span><h1>{{ __('exhibitions::ui.auto_3abf194d6c') }}</h1><p>{{ __('exhibitions::ui.auto_7d01b70a14') }}</p></div>@can('exhibitions.virtual_rooms.manage')<a class="z4-exhibitions-button" href="{{ route('admin.plugins.exhibitions.virtual.rooms.create') }}">{{ __('exhibitions::ui.auto_c667beaddc') }}</a>@endcan</header>
@if(session('status'))<div class="z4-exhibitions-alert is-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="z4-exhibitions-alert is-warning">{{ $errors->first() }}</div>@endif
<section class="z4-exhibitions-panel z4-ve-room-list">
@forelse($rooms as $room)
<article class="z4-ve-room-row">
    <div><span class="z4-exhibitions-status is-{{ $room->status === 'published' ? 'ready' : 'blocked' }}">{{ ucfirst($room->status) }}</span><h2>{{ $room->title }}</h2><p>{{ $room->short_description ?: 'No room description yet.' }}</p></div>
    <dl><div><dt>{{ __('exhibitions::ui.auto_87f16aef00') }}</dt><dd>{{ $room->panorama_path ? number_format($room->panorama_width).'×'.number_format($room->panorama_height) : 'Missing' }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_f573ad6f99') }}</dt><dd>{{ __('exhibitions::ui.auto_9b3ac497c0') }}</dd></div><div><dt>{{ __('exhibitions::ui.auto_0fe5025af5') }}</dt><dd>{{ $room->published_artworks }}</dd></div></dl>
    <div class="z4-ve-actions"><a href="{{ route('admin.plugins.exhibitions.virtual.rooms.edit',$room->public_id) }}">{{ __('exhibitions::ui.auto_7dce122004') }}</a><a href="{{ route('admin.plugins.exhibitions.virtual.rooms.layout',$room->public_id) }}">{{ __('exhibitions::ui.auto_d951edb1b9') }}</a><a href="{{ route('admin.plugins.exhibitions.virtual.requests.index',['room'=>$room->public_id]) }}">{{ __('exhibitions::ui.auto_b134a801a1') }}</a></div>
</article>
@empty<div class="z4-exhibitions-empty"><strong>{{ __('exhibitions::ui.auto_0d68530320') }}</strong><p>{{ __('exhibitions::ui.auto_510d2d03f6') }}</p></div>@endforelse
</section><x-platform-pagination :paginator="$rooms" />
</div></main></x-app-layout>
