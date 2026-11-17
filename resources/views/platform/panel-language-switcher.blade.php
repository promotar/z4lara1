@php
    $languages = $platformLanguages ?? ['en' => 'English', 'ar' => 'العربية'];
    $currentLocale = app()->getLocale();
@endphp

<div class="platform-locale-switch" dir="ltr" role="group" aria-label="{{ __('core.common.language') }}">
    @foreach ($languages as $code => $label)
        <form method="POST" action="{{ route('front.account.language') }}" class="platform-locale-switch__form">
            @csrf
            <input type="hidden" name="locale" value="{{ $code }}">
            <button type="submit"
                    class="{{ $currentLocale === $code ? 'is-active' : '' }}"
                    lang="{{ $code }}"
                    @if ($currentLocale === $code) aria-current="true" @endif>{{ $label }}</button>
        </form>
        @if (! $loop->last)
            <span class="platform-locale-switch__divider" aria-hidden="true">/</span>
        @endif
    @endforeach
</div>

@once
    <style>
        .platform-locale-switch__form{display:inline;margin:0}
        .platform-locale-switch button{
            background:none;border:0;padding:.1rem .15rem;margin:0;cursor:pointer;font:inherit;
            color:inherit;opacity:.65;text-decoration:none;line-height:1;
        }
        .platform-locale-switch button:hover{opacity:1;text-decoration:underline;text-underline-offset:3px}
        .platform-locale-switch button.is-active{opacity:1;font-weight:800;text-decoration:underline;text-underline-offset:3px}
    </style>
@endonce
