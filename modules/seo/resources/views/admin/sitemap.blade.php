<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('seo::ui.sitemap') }}</h2>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.plugins.seo.sitemap.generate') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('seo::ui.generate_now') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.plugins.seo.sitemap.validate') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.validate') }}</button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.status') }}</p>
                    <p class="mt-2 text-xl font-bold text-gray-900">{{ $settings['enabled'] ? ($run?->status ?? __('seo::ui.never_generated')) : __('seo::ui.sitemap_disabled') }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.urls') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $run?->urls_count ?? 0 }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.chunks') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $run?->chunks_count ?? 0 }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.last_generated') }}</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900">{{ $run?->completed_at?->diffForHumans() ?? '—' }}</p>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-gray-600">{{ __('seo::ui.sitemap_hint') }}</p>
                    <a href="{{ url('/sitemap.xml') }}" target="_blank" rel="noopener" class="font-semibold text-gray-900 underline">{{ url('/sitemap.xml') }}</a>
                </div>
                @if ($run?->error)
                    <p class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $run->error }}</p>
                @endif
            </div>

            <form method="POST" action="{{ route('admin.plugins.seo.sitemap.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">{{ __('seo::ui.sitemap_settings') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.sitemap_settings_help') }}</p>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="enabled" value="0">
                            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings['enabled']))>
                            {{ __('seo::ui.enable_sitemap') }}
                        </label>

                        <div>
                            <label for="seo_sitemap_max_urls" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.max_urls') }}</label>
                            <input id="seo_sitemap_max_urls" type="number" min="1" max="50000" name="max_urls" value="{{ old('max_urls', $settings['max_urls']) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.max_urls_help') }}</p>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="include_images" value="0">
                            <input type="checkbox" name="include_images" value="1" @checked(old('include_images', $settings['include_images']))>
                            {{ __('seo::ui.include_images') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="include_featured_images" value="0">
                            <input type="checkbox" name="include_featured_images" value="1" @checked(old('include_featured_images', $settings['include_featured_images']))>
                            {{ __('seo::ui.include_featured_images') }}
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="hidden" name="exclude_attachments" value="0">
                            <input type="checkbox" name="exclude_attachments" value="1" @checked(old('exclude_attachments', $settings['exclude_attachments']))>
                            {{ __('seo::ui.exclude_attachments') }}
                        </label>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">{{ __('seo::ui.content_types') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.content_types_help') }}</p>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($contentTypes as $key => $label)
                            <label class="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-4 py-3 text-sm text-gray-700">
                                <span>{{ $label }}</span>
                                <input type="hidden" name="types[{{ $key }}]" value="0">
                                <input type="checkbox" name="types[{{ $key }}]" value="1" @checked(old('types.'.$key, $settings['types'][$key] ?? true))>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('seo::ui.no_content_types') }}</p>
                        @endforelse
                    </div>
                </section>

                <div>
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('seo::ui.save') }}</button>
                </div>
            </form>

            @if ($chunks->isNotEmpty())
                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-800">{{ __('seo::ui.sitemap_files') }}</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">#</th>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.urls') }}</th>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.bytes') }}</th>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($chunks as $chunk)
                                <tr>
                                    <td class="px-4 py-3">{{ $chunk->chunk_index }}</td>
                                    <td class="px-4 py-3">{{ $chunk->urls_count }}</td>
                                    <td class="px-4 py-3">{{ number_format($chunk->bytes) }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ url('/sitemap/'.$chunk->chunk_index.'.xml') }}" target="_blank" rel="noopener" class="font-semibold text-gray-700 hover:text-gray-950">{{ __('seo::ui.view') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
