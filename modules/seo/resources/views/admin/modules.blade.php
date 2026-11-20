<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('seo::ui.modules') }}</h2>
    </x-slot>

    <div class="py-8" x-data="{ locale: '{{ $locale }}' }">
        <style>[x-cloak] { display: none !important; }</style>

        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ __('seo::ui.module_fields') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.module_fields_help') }}</p>
                        <p class="mt-2 text-xs text-gray-500">{{ __('seo::ui.module_language_help') }}</p>
                    </div>

                    @if (count($locales) > 1)
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-gray-500">{{ __('seo::ui.module_language') }}:</span>

                            @foreach ($locales as $code => $label)
                                <button
                                    type="button"
                                    @click="locale = '{{ $code }}'"
                                    :style="locale === '{{ $code }}'
                                        ? 'background-color: #991b1b !important; border-color: #991b1b !important; color: #ffffff !important; transition: none !important;'
                                        : 'background-color: #ffffff !important; border-color: #d1d5db !important; color: #374151 !important; transition: none !important;'"
                                    :aria-pressed="locale === '{{ $code }}' ? 'true' : 'false'"
                                    dir="ltr"
                                    class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium"
                                >
                                    <span>{{ $label }}</span>
                                    <span class="text-xs opacity-75">{{ strtoupper($code) }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('admin.plugins.seo.modules.update') }}" class="space-y-5">
                @csrf
                @method('PUT')
                <input type="hidden" name="locale" :value="locale">

                @foreach ($locales as $code => $languageLabel)
                    <div x-show="locale === '{{ $code }}'" x-cloak class="space-y-5">
                        @forelse ($modules as $module)
                            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-5 py-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="font-semibold text-gray-900">{{ $module['label'] }}</h3>
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">{{ $languageLabel }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ $module['slug'] }}</p>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <span class="font-semibold text-gray-700">{{ __('seo::ui.active_content_types') }}:</span>
                                        {{ implode(', ', $module['types']) }}
                                    </div>
                                </div>

                                <div class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($fields as $field => $label)
                                        <label class="block rounded-md border border-gray-200 px-3 py-3">
                                            <span class="block text-sm font-medium text-gray-800">{{ __('seo::ui.module_field_'.$field) }}</span>
                                            <input
                                                type="text"
                                                dir="auto"
                                                name="modules[{{ $module['slug'] }}][{{ $code }}][{{ $field }}]"
                                                value="{{ old('modules.'.$module['slug'].'.'.$code.'.'.$field, $module['fields_by_locale'][$code][$field] ?? '') }}"
                                                class="mt-2 block w-full rounded-md border-gray-300 shadow-sm text-sm"
                                                placeholder="{{ $module['fields_by_locale'][$code][$field] ?? '' }}"
                                            >
                                            <span class="mt-1 block text-xs text-gray-500">
                                                @switch($field)
                                                    @case('seo_title') {seo_title} · {content_title} · {site_name} @break
                                                    @case('meta_description') {meta_description} · {content_description} @break
                                                    @case('focus_keyword') {focus_keyword} — {{ __('seo::ui.focus_keyword_help') }} @break
                                                    @case('robots') {robots} @break
                                                    @case('canonical') {canonical_url} @break
                                                    @case('social_preview') {seo_title} · {meta_description} @break
                                                    @case('image_seo_title') {content_title} · {seo_title} @break
                                                    @case('image_seo_description') {content_description} · {meta_description} @break
                                                    @default {{ __('seo::ui.module_field_sources_help') }}
                                                @endswitch
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                @if ($module['sample_keywords'] !== [])
                                    <div class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500">
                                        <span class="font-semibold text-gray-700">{{ __('seo::ui.top_keywords') }}:</span>
                                        {{ implode(' · ', $module['sample_keywords']) }}
                                    </div>
                                @endif
                            </section>
                        @empty
                            <div class="rounded-lg border border-gray-200 bg-white px-5 py-8 text-center text-sm text-gray-500">
                                {{ __('seo::ui.no_active_modules') }}
                            </div>
                        @endforelse
                    </div>
                @endforeach

                @if ($modules !== [])
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-md px-4 py-2 text-sm font-semibold text-white" style="background-color: #991b1b;">
                            {{ __('seo::ui.save') }}
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-app-layout>
