<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $schemaTemplate->exists ? __('seo::ui.edit_schema') : __('seo::ui.add_schema') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 ps-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (!empty($preview))
                <div class="rounded-lg border {{ empty($preview['errors']) ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' }} p-5 text-sm">
                    <p class="font-semibold {{ empty($preview['errors']) ? 'text-green-800' : 'text-red-800' }}">
                        {{ empty($preview['errors']) ? __('seo::ui.schema_preview_valid') : __('seo::ui.schema_preview_invalid') }}
                    </p>
                    @if (!empty($preview['errors']))
                        <ul class="mt-2 list-disc space-y-1 ps-5 text-red-800">
                            @foreach ($preview['errors'] as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if (!empty($preview['schema']))
                        <pre class="mt-3 overflow-x-auto rounded-md bg-white p-4 text-xs text-gray-800" dir="ltr">{{ json_encode($preview['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif
                </div>
            @endif

            <form method="POST" action="{{ $schemaTemplate->exists ? route('admin.plugins.seo.schemas.update', $schemaTemplate) : route('admin.plugins.seo.schemas.store') }}" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                @if ($schemaTemplate->exists)
                    @method('PATCH')
                @endif
                <input type="hidden" name="record_id" value="{{ $schemaTemplate->id }}">

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="schema-name" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.name') }}</label>
                        <input id="schema-name" name="name" value="{{ old('name', $schemaTemplate->name) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                    </div>
                    <div>
                        <label for="schema-key" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.key') }}</label>
                        <input id="schema-key" name="key" value="{{ old('key', $schemaTemplate->key) }}" placeholder="blog-post-default" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm" required>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="schema-module" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.module') }}</label>
                        <input id="schema-module" name="module_slug" value="{{ old('module_slug', $schemaTemplate->module_slug) }}" placeholder="blog" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <label for="schema-route" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.route_pattern') }}</label>
                        <input id="schema-route" name="route_pattern" value="{{ old('route_pattern', $schemaTemplate->route_pattern) }}" placeholder="blog.show" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm">
                    </div>
                    <div>
                        <label for="schema-priority" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.priority') }}</label>
                        <input id="schema-priority" type="number" min="-100" max="100" name="priority" value="{{ old('priority', $schemaTemplate->priority ?? 0) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>

                <div>
                    <label for="schema-json" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.schema_json') }}</label>
                    <textarea id="schema-json" name="schema_json" rows="16" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-xs shadow-sm" dir="ltr" required>{{ old('schema_json', $schemaTemplate->schema_json) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.schema_json_help') }}</p>
                </div>

                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $schemaTemplate->is_active ?? true))>
                    {{ __('seo::ui.active') }}
                </label>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('seo::ui.save') }}</button>
                    <button type="submit" formaction="{{ route('admin.plugins.seo.schemas.preview') }}" formmethod="POST" formnovalidate class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.validate_preview') }}</button>
                    <a href="{{ route('admin.plugins.seo.schemas.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
