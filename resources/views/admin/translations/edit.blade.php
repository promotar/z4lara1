<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ __('core.translations.edit_title') }}
                    <span class="ml-2 rounded bg-gray-100 px-2 py-0.5 font-mono text-sm text-gray-600">{{ $locale }}</span>
                    <span class="ml-1 font-mono text-sm text-gray-500">{{ $module ? $module.'/' : '' }}core.txt</span>
                </h2>
                <p class="mt-1 font-mono text-xs text-gray-400">{{ $path }}</p>
            </div>
            <a href="{{ route('admin.translations.index') }}" class="tr-back" style="background:#ffffff !important;color:#334155 !important;border:1px solid #cbd5e1 !important;text-decoration:none !important">
                &larr; {{ __('core.translations.back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.translations.raw') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $locale }}">
                <input type="hidden" name="group" value="{{ $group }}">
                @if ($module)
                    <input type="hidden" name="module" value="{{ $module }}">
                @endif

                <div class="overflow-hidden rounded-md border border-gray-300 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3">
                        <div class="flex items-center gap-4 text-xs text-gray-500">
                            <span class="font-mono">{{ $path }}</span>
                            <span id="tr-line-count">{{ substr_count($content, "\n") }} {{ __('core.translations.lines') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs text-gray-500" data-i18n="core.translations.raw_hint">{{ __('core.translations.raw_hint') }}</span>
                            <button type="submit" class="tr-save" style="background:#b91c1c !important;color:#ffffff !important;border:1px solid #b91c1c !important" data-i18n="core.translations.save_all">
                                {{ __('core.translations.save_all') }}
                            </button>
                        </div>
                    </div>

                    <textarea
                        id="tr-content"
                        name="content"
                        rows="30"
                        spellcheck="false"
                        dir="ltr"
                        style="display:block;width:100%;min-height:60vh;border:0;outline:0;resize:vertical;padding:1rem 1.25rem;background:#0f172a;color:#e2e8f0;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,'Liberation Mono',monospace;font-size:13px;line-height:1.7;white-space:pre;tab-size:4;"
                    >{{ $content }}</textarea>
                </div>
            </form>
        </div>
    </div>

    @once
        <style>
            .tr-back {
                display: inline-flex; align-items: center; gap: .35rem;
                border-radius: .375rem; padding: .45rem .8rem;
                font-size: .8rem; font-weight: 600;
            }
            .tr-back:hover { background: #f8fafc !important; }
            .tr-save {
                border-radius: .375rem; padding: .5rem .95rem;
                font-size: .8rem; font-weight: 700; cursor: pointer;
            }
            .tr-save:hover { background: #991b1b !important; }
            #tr-content::selection { background: #b91c1c; color: #fff; }
        </style>
    @endonce

    <script>
        (function () {
            const textarea = document.getElementById('tr-content');
            const count = document.getElementById('tr-line-count');

            if (!textarea || !count) return;

            textarea.addEventListener('input', () => {
                const lines = textarea.value.split('\n').filter((line) => line.trim() !== '').length;
                count.textContent = lines + ' {{ __('core.translations.lines') }}';
            });
        })();
    </script>
</x-app-layout>
