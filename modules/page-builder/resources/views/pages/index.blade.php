<x-app-layout>
    <style>
        /* Pages table: explicit checkbox styling (the admin theme relies on a
           ::before checkmark that Tailwind's appearance reset can hide). */
        .pb-pages-table input[type="checkbox"] {
            -webkit-appearance: none;
            appearance: none;
            width: 18px;
            height: 18px;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 5px;
            background: #ffffff !important;
            display: inline-grid;
            place-content: center;
            cursor: pointer;
            vertical-align: middle;
            transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
        }

        .pb-pages-table input[type="checkbox"]::before {
            content: "";
            width: 10px;
            height: 10px;
            background: #ffffff;
            clip-path: polygon(14% 50%, 0 64%, 39% 100%, 100% 18%, 84% 4%, 36% 68%);
            transform: scale(0);
            transition: transform .12s ease;
        }

        .pb-pages-table input[type="checkbox"]:checked {
            background: #d71913 !important;
            border-color: #b31410 !important;
        }

        .pb-pages-table input[type="checkbox"]:checked::before {
            transform: scale(1);
        }

        .pb-pages-table input[type="checkbox"]:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(215, 25, 19, .18);
        }

        /* Language chips + edit hover menu */
        .pb-lang-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
        .pb-lang-chip {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 1px 7px; border-radius: 999px; font-size: 10px; font-weight: 700;
            background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; letter-spacing: .04em;
        }
        .pb-lang-chip.is-base { background: #fff7ed; color: #9a3412; border-color: #fed7aa; }
        .pb-lang-chip.is-translated { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }

        .pb-lang-edit { position: relative; display: inline-block; }
        .pb-lang-edit__menu {
            position: absolute; inset-inline-end: 0; top: calc(100% + 6px);
            min-width: 220px; padding: 6px; border-radius: 10px;
            background: #ffffff; border: 1px solid #e5e7eb;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .18);
            display: none; z-index: 60;
        }
        /* The menu is moved to <body> on hover (portal) so the card/table
           overflow can never clip it. */
        .pb-lang-edit__menu.is-portal { position: fixed !important; z-index: 9999 !important; display: block; }
        .pb-lang-edit__menu a {
            display: flex; align-items: center; justify-content: space-between; gap: 10px;
            padding: 7px 9px; border-radius: 7px; font-size: 12px; color: #1f2937; text-decoration: none;
        }
        .pb-lang-edit__menu a:hover { background: #fff1f2; color: #991b1b; }
        .pb-lang-edit__menu .pb-lang-code { font-family: ui-monospace, monospace; font-size: 10px; color: #94a3b8; }
        .pb-lang-edit__menu .pb-lang-state { font-size: 10px; font-weight: 700; }
        .pb-lang-edit__menu .is-new { color: #b45309; }
        .pb-lang-edit__menu .is-done { color: #047857; }
        .pb-lang-edit__menu .is-base { color: #9a3412; }
    </style>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('core.page_builder.pages.title') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ __('core.page_builder.pages.subtitle') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.vvveb.layout') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    {{ __('core.page_builder.pages.theme_layout') }}
                </a>
                @foreach ([
                    'page' => __('core.page_builder.pages.create_page'),
                    'header' => __('core.page_builder.pages.create_header'),
                    'footer' => __('core.page_builder.pages.create_footer'),
                    'block' => __('core.page_builder.pages.create_block'),
                ] as $type => $label)
                    <form method="POST" action="{{ route('admin.pages.store') }}">
                        @csrf
                        <input type="hidden" name="content_type" value="{{ $type }}">
                        <button class="rounded-md {{ $type === 'page' ? 'bg-gray-900 text-white hover:bg-gray-800' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }} px-4 py-2 text-sm font-semibold">
                            {{ $label }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <section
                class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"
                x-data="{
                    selected: [],
                    allIds: @js($pages->pluck('id')->map(fn ($id) => (string) $id)->values()),
                    get allSelected() {
                        return this.allIds.length > 0 && this.selected.length === this.allIds.length;
                    },
                    toggleAll() {
                        this.selected = this.allSelected ? [] : [...this.allIds];
                    },
                    clear() {
                        this.selected = [];
                    }
                }"
            >
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('core.page_builder.pages.designs') }}</h3>
                            @if (($search ?? '') !== '')
                                <p class="mt-1 text-sm text-gray-500">{{ __('core.page_builder.pages.search_results', ['term' => $search]) }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <form
                                id="bulk-pages-delete-form"
                                method="POST"
                                action="{{ route('admin.pages.bulk-destroy') }}"
                                x-show="selected.length > 0"
                                x-cloak
                                onsubmit="return confirm(@js(__('core.page_builder.pages.delete_selected_confirm')))"
                                class="flex flex-wrap items-center gap-2"
                            >
                                @csrf
                                @method('DELETE')
                                <button class="rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                    {{ __('core.page_builder.pages.delete_selected') }}
                                </button>
                                <button type="button" @click="clear()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    {{ __('core.page_builder.pages.cancel') }}
                                </button>
                                <span class="text-sm text-gray-500"><span x-text="selected.length"></span> {{ __('core.page_builder.pages.selected') }}</span>
                            </form>
                            <form method="GET" action="{{ route('admin.pages.index') }}" class="flex flex-wrap items-center justify-end gap-2">
                                <label for="page-search" class="sr-only">{{ __('core.page_builder.pages.search_label') }}</label>
                                <input
                                    id="page-search"
                                    type="search"
                                    name="search"
                                    value="{{ $search ?? '' }}"
                                    placeholder="{{ __('core.page_builder.pages.search_placeholder') }}"
                                    class="w-64 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                    {{ __('core.page_builder.pages.search') }}
                                </button>
                                @if (($search ?? '') !== '')
                                    <a href="{{ route('admin.pages.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        {{ __('core.page_builder.pages.clear') }}
                                    </a>
                                @endif
                            </form>
                            <span class="text-sm text-gray-500">{{ __('core.page_builder.pages.total', ['count' => $pages->total()]) }}</span>
                        </div>
                    </div>
                </div>

                @if ($pages->isEmpty())
                    <div class="px-6 py-8 text-sm text-gray-600">
                        @if (($search ?? '') !== '')
                            {{ __('core.page_builder.pages.empty_search') }}
                        @else
                            {{ __('core.page_builder.pages.empty') }}
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="pb-pages-table min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="w-12 px-6 py-3">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            :checked="allSelected"
                                            @change="toggleAll()"
                                            aria-label="{{ __('core.page_builder.pages.select_all') }}"
                                        >
                                    </th>
                                    <th class="px-6 py-3">{{ __('core.page_builder.pages.col_title') }}</th>
                                    <th class="px-6 py-3">{{ __('core.page_builder.pages.col_type') }}</th>
                                    <th class="px-6 py-3">{{ __('core.page_builder.pages.col_slug') }}</th>
                                    <th class="px-6 py-3">{{ __('core.page_builder.pages.col_status') }}</th>
                                    <th class="px-6 py-3">{{ __('core.page_builder.pages.col_updated') }}</th>
                                    <th class="px-6 py-3 text-right">{{ __('core.page_builder.pages.col_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($pages as $page)
                                    @php
                                        $isPublicPage = ($page->content_type ?? 'page') === 'page';
                                        $isPublished = $page->status === 'published';
                                        $viewUrl = $isPublicPage
                                            ? route('pages.show', $page->slug, false)
                                            : route('admin.pages.preview', $page->id, false);
                                        $previewUrl = route('admin.pages.preview', $page->id, false);
                                        $pageLocaleRows = ($pageTranslations ?? collect())->get($page->id, collect())->keyBy('locale');
                                    @endphp
                                    <tr>
                                        <td class="px-6 py-4 align-top">
                                            <input
                                                form="bulk-pages-delete-form"
                                                type="checkbox"
                                                name="pages[]"
                                                value="{{ $page->id }}"
                                                x-model="selected"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                aria-label="{{ __('core.page_builder.pages.select_page', ['title' => $page->title]) }}"
                                            >
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $page->title }}</div>
                                            @if ($page->seo_title)
                                                <div class="mt-1 text-xs text-gray-500">{{ $page->seo_title }}</div>
                                            @endif
                                            <div class="pb-lang-chips">
                                                <span class="pb-lang-chip is-base" title="{{ __('core.page_builder.pages.language_base') }}">{{ strtoupper($defaultLocale) }}</span>
                                                @foreach ($pageLocaleRows as $code => $row)
                                                    <span class="pb-lang-chip {{ ($row->status ?? '') === 'published' ? 'is-translated' : '' }}">{{ strtoupper($code) }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                {{ \App\Platform\Core\Localization\Translation::label('core.page_builder.type.'.($page->content_type ?? 'page'), ucfirst($page->content_type ?? 'page')) }}
                                            </span>
                                            @if (($page->content_type ?? 'page') === 'block' && ($page->block_key ?? null))
                                                <div class="mt-1 font-mono text-xs text-gray-500">{{ $page->block_key }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 font-mono text-xs text-gray-600">/pages/{{ $page->slug }}</td>
                                        <td class="px-6 py-4">
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                                'bg-green-100 text-green-700' => $page->status === 'published',
                                                'bg-yellow-100 text-yellow-800' => $page->status !== 'published',
                                            ])>
                                                {{ \App\Platform\Core\Localization\Translation::label('core.page_builder.status.'.$page->status, ucfirst($page->status)) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">{{ $page->updated_at }}</td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                @if (! $isPublicPage || $isPublished)
                                                    <a href="{{ $viewUrl }}" target="_blank" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                        {{ __('core.page_builder.pages.view') }}
                                                    </a>
                                                @endif
                                                <a href="{{ $previewUrl }}" target="_blank" class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">
                                                    {{ __('core.page_builder.pages.preview') }}
                                                </a>
                                                <div class="pb-lang-edit">
                                                    <a href="{{ route('admin.pages.edit', ['page' => $page->id, 'locale' => $defaultLocale]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                        {{ __('core.page_builder.pages.edit') }}
                                                    </a>
                                                    <div class="pb-lang-edit__menu" role="menu" aria-label="{{ __('core.page_builder.pages.edit_language_hint') }}">
                                                        <div style="padding:4px 9px 6px;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;">{{ __('core.page_builder.pages.edit_language_hint') }}</div>
                                                        @foreach (($installLanguages ?? []) as $code => $label)
                                                            @php $hasRow = $pageLocaleRows->has($code); $isBaseLang = $code === $defaultLocale; @endphp
                                                            <a href="{{ route('admin.pages.edit', ['page' => $page->id, 'locale' => $code]) }}" role="menuitem">
                                                                <span>{{ $label }} <span class="pb-lang-code">{{ $code }}</span></span>
                                                                @if ($isBaseLang)
                                                                    <span class="pb-lang-state is-base">{{ __('core.page_builder.pages.language_base') }}</span>
                                                                @elseif ($hasRow)
                                                                    <span class="pb-lang-state is-done">{{ __('core.page_builder.pages.language_translated') }}</span>
                                                                @else
                                                                    <span class="pb-lang-state is-new">{{ __('core.page_builder.pages.language_new') }}</span>
                                                                @endif
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                </div>
                                                <form method="POST" action="{{ route('admin.pages.destroy', $page->id) }}" onsubmit="return confirm(@js(__('core.page_builder.pages.delete_confirm')))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                        {{ __('core.page_builder.pages.delete') }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <x-platform-pagination :paginator="$pages" />
            </section>
        </div>
    </div>
<script>
(function () {
    if (window.__pbLangMenuInit) return;
    window.__pbLangMenuInit = true;

    var active = null;
    var hideTimer = null;

    function menuFor(wrap) {
        if (!wrap.__pbMenu) {
            wrap.__pbMenu = wrap.querySelector('.pb-lang-edit__menu');
        }
        return wrap.__pbMenu;
    }

    function close() {
        if (active && active.menu) {
            active.menu.style.display = 'none';
        }
        active = null;
    }

    function open(wrap) {
        var menu = menuFor(wrap);
        if (!menu) return;
        if (active && active.menu !== menu) close();
        if (menu.parentElement !== document.body) {
            document.body.appendChild(menu);
        }
        active = { wrap: wrap, menu: menu };

        menu.classList.add('is-portal');
        menu.style.display = 'block';

        var rect = wrap.getBoundingClientRect();
        var width = Math.max(220, Math.min(280, menu.offsetWidth || 240));
        var height = menu.offsetHeight || 200;
        var rtl = (document.documentElement.getAttribute('dir') || '').toLowerCase() === 'rtl';
        var left = rtl ? rect.left : rect.right - width;
        left = Math.max(8, Math.min(left, window.innerWidth - width - 8));

        var top = rect.bottom + 6;
        if (top + height > window.innerHeight - 8) {
            top = Math.max(8, rect.top - height - 6);
        }

        menu.style.width = width + 'px';
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }

    document.addEventListener('mouseover', function (event) {
        var wrap = event.target.closest ? event.target.closest('.pb-lang-edit') : null;

        if (wrap) {
            clearTimeout(hideTimer);
            if (!active || active.wrap !== wrap) open(wrap);
            return;
        }

        if (event.target.closest && event.target.closest('.pb-lang-edit__menu')) {
            clearTimeout(hideTimer);
        }
    });

    document.addEventListener('mouseout', function (event) {
        var related = event.relatedTarget;
        var staysInside = related && related.closest
            && (related.closest('.pb-lang-edit') || related.closest('.pb-lang-edit__menu'));

        if (staysInside) return;

        var leaves = (event.target.closest && event.target.closest('.pb-lang-edit'))
            || (event.target.closest && event.target.closest('.pb-lang-edit__menu'));

        if (leaves) {
            hideTimer = setTimeout(close, 150);
        }
    });

    document.addEventListener('focusin', function (event) {
        var wrap = event.target.closest ? event.target.closest('.pb-lang-edit') : null;
        if (wrap) { clearTimeout(hideTimer); open(wrap); }
    });

    window.addEventListener('scroll', close, true);
    window.addEventListener('resize', close);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') close();
    });
})();
</script>
</x-app-layout>
