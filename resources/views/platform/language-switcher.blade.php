@php
    $languages = $platformLanguages ?? ['en' => 'English', 'ar' => 'العربية'];
    $currentLocale = $platformLocale ?? app()->getLocale();

    // Route language changes through the locale controller so signed-in users
    // also update their stored locale. The controller redirects back to the
    // current page using the referer.
    $urlFor = static function (string $code): string {
        $current = request()->getRequestUri();

        return route('locale.switch', array_filter([
            'locale' => $code,
            'to' => $current !== '' ? $current : null,
        ]));
    };
@endphp

<div class="platform-locale-switch" dir="ltr" role="group" aria-label="{{ __('core.common.language') }}">
    @foreach ($languages as $code => $label)
        <a href="{{ $urlFor($code) }}"
           class="{{ $currentLocale === $code ? 'is-active' : '' }}"
           lang="{{ $code }}"
           hreflang="{{ $code }}"
           @if ($currentLocale === $code) aria-current="true" @endif>{{ $label }}</a>
        @if (! $loop->last)
            <span class="platform-locale-switch__divider" aria-hidden="true">/</span>
        @endif
    @endforeach
</div>

@once
    <style>
        .platform-locale-switch {
            display: inline-flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .35rem;
            font-size: .8rem;
            font-weight: 700;
            letter-spacing: .03em;
            line-height: 1;
        }
        .platform-locale-switch a {
            color: inherit;
            opacity: .58;
            text-decoration: none;
            padding: .32rem .55rem;
            border: 1px solid transparent;
            border-radius: .4rem;
        }
        .platform-locale-switch a:hover { opacity: .9; }
        .platform-locale-switch a.is-active {
            opacity: 1;
            color: #991b1b;
            background: rgba(153, 27, 27, .08);
            border-color: rgba(153, 27, 27, .22);
        }
        .platform-locale-switch__divider { opacity: .35; }
    </style>
@endonce
