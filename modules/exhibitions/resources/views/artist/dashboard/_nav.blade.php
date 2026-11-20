@php
    // Sidebar palette comes from the admin theme settings (Admin Theme plugin),
    // never from hard-coded values.
    $themeValues = [];

    try {
        if (class_exists(\Modules\ArtInpaAdminProTheme\ThemeSettings::class)) {
            $themeValues = app(\Modules\ArtInpaAdminProTheme\ThemeSettings::class)->values();
        }
    } catch (\Throwable) {
        $themeValues = [];
    }

    $sidebarBg = $themeValues['sidebar_background'] ?? null;
    $sidebarText = $themeValues['sidebar_text_color'] ?? null;
    $menuActive = $themeValues['active_menu_color'] ?? null;

    // The active item keeps the configured colour; its label flips between
    // white and a dark neutral based on the measured luminance so the text is
    // always readable, whatever colour the settings hold.
    $activeForeground = null;

    if (is_string($menuActive) && preg_match('/^#([0-9a-f]{6})$/i', $menuActive, $hexMatch)) {
        $channels = [
            hexdec(substr($hexMatch[1], 0, 2)),
            hexdec(substr($hexMatch[1], 2, 2)),
            hexdec(substr($hexMatch[1], 4, 2)),
        ];

        $linear = array_map(
            fn (int $channel): float => ($channel / 255) <= 0.03928
                ? ($channel / 255) / 12.92
                : ((($channel / 255) + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        $luminance = 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
        // 0.179 is the luminance where white and near-black reach the same
        // contrast ratio against the active colour; pick the readable one.
        $activeForeground = $luminance > 0.179 ? '#1f1a17' : '#ffffff';
    }

    $sidebarVars = array_filter([
        '--artist-sidebar-bg' => is_string($sidebarBg) ? $sidebarBg : null,
        '--artist-sidebar-fg' => is_string($sidebarText) ? $sidebarText : null,
        '--artist-menu-active' => is_string($menuActive) ? $menuActive : null,
        '--artist-menu-active-fg' => $activeForeground,
    ]);

    $sidebarStyle = collect($sidebarVars)->map(fn ($value, $name) => $name.': '.$value)->implode('; ');
@endphp
<header class="z4-artist-dashboard-header"><div><span class="z4-exhibitions-eyebrow">{{ __('exhibitions::ui.auto_97835788db') }}</span><h1>{{ $title }}</h1>@if(filled($subtitle ?? null))<p>{{ $subtitle }}</p>@endif</div><span class="z4-artist-header-actions"><a class="z4-artist-back-link" href="{{ url('/account') }}">&larr; {{ __('exhibitions::ui.my_account') }}</a><span class="z4-artist-account-chip">{{ $context->artistic_name ?: $context->legal_name }}</span></span></header>
@if($context->account_state === 'restricted')<div class="z4-exhibitions-alert is-warning"><strong>{{ __('exhibitions::ui.auto_03f8847b78') }}</strong><p>{{ __('exhibitions::ui.auto_7ddc19cffa') }}</p></div>@endif
@php($storeAvailable = (bool) ($navCounts['store_available'] ?? false))
<aside class="z4-artist-dashboard-sidebar" aria-label="{{ __('exhibitions::ui.auto_4833ee5f36') }}"@if($sidebarStyle !== '') style="{{ $sidebarStyle }}" @endif>
    <nav class="z4-artist-dashboard-sidebar-nav">
        <a @class(['is-active'=>$activeTab==='overview']) @if($activeTab==='overview') aria-current="page" @endif href="{{ route('exhibitions.dashboard.overview') }}"><span class="z4-artist-nav-icon" aria-hidden="true">{!! \App\Platform\Core\UI\IconSet::render('grid') !!}</span><strong>{{ __('exhibitions::ui.auto_3b878279a0') }}</strong></a>
        @if($storeAvailable)
            <a @class(['is-active'=>$activeTab==='artworks']) @if($activeTab==='artworks') aria-current="page" @endif href="{{ route('exhibitions.dashboard.artworks') }}"><span class="z4-artist-nav-icon" aria-hidden="true">{!! \App\Platform\Core\UI\IconSet::render('image') !!}</span><strong>{{ __('exhibitions::ui.auto_4660cacd76') }}</strong><b>{{ $navCounts['artworks'] ?? 0 }}</b></a>
            <a @class(['is-active'=>$activeTab==='virtual']) @if($activeTab==='virtual') aria-current="page" @endif href="{{ route('exhibitions.dashboard.virtual.index', ['tab' => 'available']) }}"><span class="z4-artist-nav-icon" aria-hidden="true">{!! \App\Platform\Core\UI\IconSet::render('layers') !!}</span><strong>{{ __('exhibitions::ui.auto_3abf194d6c') }}</strong></a>
            @if($activeTab === 'virtual')
                @php($activeVirtualSection = $virtualSection ?? request()->query('tab', 'available'))
                <div class="z4-artist-dashboard-sidebar-subnav" aria-label="{{ __('exhibitions::ui.auto_14725d2e1d') }}">
                    <a @class(['is-active' => $activeVirtualSection === 'available']) @if($activeVirtualSection === 'available') aria-current="page" @endif href="{{ route('exhibitions.dashboard.virtual.index', ['tab' => 'available']) }}"><strong>{{ __('exhibitions::ui.auto_c91cb7d237') }}</strong><b>{{ isset($artworks) ? $artworks->count() : 0 }}</b></a>
                    <a @class(['is-active' => $activeVirtualSection === 'published']) @if($activeVirtualSection === 'published') aria-current="page" @endif href="{{ route('exhibitions.dashboard.virtual.index', ['tab' => 'published']) }}"><strong>{{ __('exhibitions::ui.auto_408b25bbf1') }}</strong><b>{{ isset($publishedPlacements) ? $publishedPlacements->count() : 0 }}</b></a>
                    <a @class(['is-active' => $activeVirtualSection === 'removed']) @if($activeVirtualSection === 'removed') aria-current="page" @endif href="{{ route('exhibitions.dashboard.virtual.index', ['tab' => 'removed']) }}"><strong>{{ __('exhibitions::ui.auto_786706dbca') }}</strong><b>{{ isset($adminRemovedPlacements) ? $adminRemovedPlacements->count() : 0 }}</b></a>
                </div>
            @endif
            <a @class(['is-active'=>$activeTab==='sales']) @if($activeTab==='sales') aria-current="page" @endif href="{{ route('exhibitions.dashboard.sales') }}"><span class="z4-artist-nav-icon" aria-hidden="true">{!! \App\Platform\Core\UI\IconSet::render('receipt') !!}</span><strong>{{ __('exhibitions::ui.auto_c490cc5df7') }}</strong><b>{{ $navCounts['sales'] ?? 0 }}</b></a>
            <a @class(['is-active'=>$activeTab==='earnings']) @if($activeTab==='earnings') aria-current="page" @endif href="{{ route('exhibitions.dashboard.earnings') }}"><span class="z4-artist-nav-icon" aria-hidden="true">{!! \App\Platform\Core\UI\IconSet::render('credit') !!}</span><strong>{{ __('exhibitions::ui.auto_9cf1061581') }}</strong></a>
        @endif
        <a @class(['is-active'=>$activeTab==='profile']) @if($activeTab==='profile') aria-current="page" @endif href="{{ route('exhibitions.dashboard.profile') }}"><span class="z4-artist-nav-icon" aria-hidden="true">{!! \App\Platform\Core\UI\IconSet::render('user') !!}</span><strong>{{ __('exhibitions::ui.auto_0c93ca4c46') }}</strong></a>
        <a @class(['is-active'=>$activeTab==='support']) @if($activeTab==='support') aria-current="page" @endif href="{{ route('exhibitions.dashboard.support') }}"><span class="z4-artist-nav-icon" aria-hidden="true">{!! \App\Platform\Core\UI\IconSet::render('message') !!}</span><strong>{{ __('exhibitions::ui.auto_db5eb84117') }}</strong><b>{{ $navCounts['support'] ?? 0 }}</b></a>
    </nav>

</aside>
<script>
(() => {
    const coreProfileUrl = @json(route('profile.edit'));
    const artistProfileUrl = @json(route('exhibitions.dashboard.profile'));
    document.querySelectorAll('a.z4-admin-profile-link').forEach((link) => {
        if (link.href === coreProfileUrl || link.getAttribute('href') === coreProfileUrl) {
            link.href = artistProfileUrl;
            link.setAttribute('aria-label', 'Open Profile and Documents');
        }
    });
})();
</script>

<script>
(function () {
    var root = document.documentElement;
    var frame = null;

    function syncRail() {
        frame = null;

        var container = document.querySelector('.z4-artist-dashboard .z4-exhibitions-container');
        var heading = container ? container.querySelector('.z4-artist-dashboard-header') : null;

        if (!container || !heading) { return; }

        var columns = getComputedStyle(container).gridTemplateColumns.split(' ');
        var railWidth = parseFloat(columns[0] || '') || 0;

        if (railWidth > 0) {
            root.style.setProperty('--artist-sidebar-width', Math.round(railWidth) + 'px');
        }

        if (window.matchMedia('(max-width: 768px)').matches) { return; }

        // The rail shares the first line with the page heading: it starts level
        // with the heading and pins to the viewport edge once the page scrolls.
        var headingRect = heading.getBoundingClientRect();
        var offset = Math.max(16, Math.round(headingRect.top));
        root.style.setProperty('--artist-header-offset', offset + 'px');

        var rtl = getComputedStyle(root).direction === 'rtl';
        var viewportWidth = document.documentElement.clientWidth || window.innerWidth;
        var containerRect = container.getBoundingClientRect();
        var padStart = parseFloat(getComputedStyle(container).paddingInlineStart) || 0;
        var inset = rtl
            ? (viewportWidth - containerRect.right + padStart)
            : (containerRect.left + padStart);

        root.style.setProperty('--artist-sidebar-inset', Math.max(0, Math.round(inset)) + 'px');
    }

    function schedule() {
        if (frame !== null) { return; }
        frame = window.requestAnimationFrame(syncRail);
    }

    syncRail();
    window.addEventListener('load', schedule);
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('orientationchange', schedule);

    // Fonts and header wrapping move the heading: measure again after they settle.
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(schedule);
    }

    if (window.ResizeObserver) {
        var siteHeader = document.querySelector('.art-hdr');
        if (siteHeader) {
            new ResizeObserver(schedule).observe(siteHeader);
        }
        new ResizeObserver(schedule).observe(document.body);
    }
})();
</script>
