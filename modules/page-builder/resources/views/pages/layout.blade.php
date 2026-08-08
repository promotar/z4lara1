<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Theme Layout</h2>
                <p class="mt-1 text-sm text-gray-500">Choose and order the published VvvebJs pages injected as website headers and footers.</p>
            </div>
            <a href="{{ route('admin.pages.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                VvvebJs Builder
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('admin.vvveb.layout.update') }}" class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                @csrf
                @method('PUT')

                <div class="border-b border-gray-200 px-6 py-5">
                    <h3 class="font-semibold text-gray-900">Active Theme Layout</h3>
                    <p class="mt-1 text-sm text-gray-500">Each selected page is rendered in the exact top-to-bottom order shown here.</p>
                </div>

                <div class="grid gap-8 p-6 lg:grid-cols-2">
                    @foreach ([
                        'headers' => ['Headers', 'Add Header', old('headers', $selectedHeaders)],
                        'footers' => ['Footers', 'Add Footer', old('footers', $selectedFooters)],
                    ] as $field => [$label, $addLabel, $selected])
                        <section
                            class="rounded-lg border border-gray-200 bg-gray-50 p-4"
                            x-data="{ rows: @js(array_values($selected)) }"
                        >
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div>
                                    <h4 class="font-semibold text-gray-900">{{ $label }}</h4>
                                    <p class="mt-1 text-xs text-gray-500">Position 1 is rendered first.</p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                                    @click="rows.push('')"
                                >
                                    {{ $addLabel }}
                                </button>
                            </div>

                            <div class="space-y-3">
                                <template x-for="(pageId, index) in rows" :key="index">
                                    <div class="flex items-center gap-2 rounded-md border border-gray-200 bg-white p-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-700" x-text="index + 1"></span>
                                        <select
                                            name="{{ $field }}[]"
                                            x-model="rows[index]"
                                            required
                                            class="min-w-0 flex-1 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                        >
                                            <option value="">Choose a published page</option>
                                            @foreach ($designs as $design)
                                                <option value="{{ $design->id }}">{{ $design->title }} · {{ ucfirst($design->content_type) }}</option>
                                            @endforeach
                                        </select>
                                        <div class="flex shrink-0 gap-1">
                                            <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-700 disabled:opacity-30" :disabled="index === 0" @click="const item = rows.splice(index, 1)[0]; rows.splice(index - 1, 0, item)" aria-label="Move up">↑</button>
                                            <button type="button" class="rounded border border-gray-300 px-2 py-1 text-sm text-gray-700 disabled:opacity-30" :disabled="index === rows.length - 1" @click="const item = rows.splice(index, 1)[0]; rows.splice(index + 1, 0, item)" aria-label="Move down">↓</button>
                                            <button type="button" class="rounded border border-red-200 px-2 py-1 text-sm text-red-700" @click="rows.splice(index, 1)" aria-label="Remove">×</button>
                                        </div>
                                    </div>
                                </template>

                                <p x-show="rows.length === 0" class="rounded-md border border-dashed border-gray-300 bg-white px-4 py-6 text-center text-sm text-gray-500">
                                    No {{ strtolower($label) }} selected.
                                </p>
                            </div>
                        </section>
                    @endforeach
                </div>

                <div class="flex items-center justify-end border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <button class="rounded-md bg-blue-600 px-5 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Save Theme Layout
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
