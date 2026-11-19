@php
    $canTranslate = auth()->check()
        && auth()->user()->hasRole('super-admin')
        && \Illuminate\Support\Facades\Route::has('admin.translations.index');
@endphp

@if ($canTranslate)
    <a href="{{ route('admin.translations.index') }}"
       class="i18n-toggle"
       title="{{ __('core.translate.button') }}"
       data-i18n="core.translate.button">
        <span aria-hidden="true">A/ع</span>
        <span>{{ __('core.translate.button') }}</span>
    </a>

    @once
        <style>
            .i18n-toggle {
                display: inline-flex;
                align-items: center;
                gap: .35rem;
                border: 1px solid currentColor;
                border-radius: 999px;
                background: transparent;
                color: inherit;
                font: inherit;
                font-size: .72rem;
                font-weight: 700;
                line-height: 1;
                padding: .38rem .62rem;
                cursor: pointer;
                text-decoration: none;
                white-space: nowrap;
            }
            .i18n-toggle:hover { background: rgba(185, 28, 28, .08); }
        </style>
    @endonce
@endif
