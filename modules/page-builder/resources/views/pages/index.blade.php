<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">VvvebJs Builder</h2>
                <p class="mt-1 text-sm text-gray-500">Design pages and reusable blocks in one VvvebJs workspace, then assign ordered headers and footers.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('admin.vvveb.layout') }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Theme Layout
                </a>
                @foreach (['page' => 'Create Body / Page', 'header' => 'Create Header', 'footer' => 'Create Footer', 'block' => 'Create Block'] as $type => $label)
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
                            <h3 class="text-lg font-semibold text-gray-900">VvvebJs Designs</h3>
                            @if (($search ?? '') !== '')
                                <p class="mt-1 text-sm text-gray-500">Showing results for "{{ $search }}".</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <form
                                id="bulk-pages-delete-form"
                                method="POST"
                                action="{{ route('admin.pages.bulk-destroy') }}"
                                x-show="selected.length > 0"
                                x-cloak
                                onsubmit="return confirm('Delete selected pages?');"
                                class="flex flex-wrap items-center gap-2"
                            >
                                @csrf
                                @method('DELETE')
                                <button class="rounded-md border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50">
                                    Delete Selected
                                </button>
                                <button type="button" @click="clear()" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <span class="text-sm text-gray-500" x-text="selected.length + ' selected'"></span>
                            </form>
                            <form method="GET" action="{{ route('admin.pages.index') }}" class="flex flex-wrap items-center justify-end gap-2">
                                <label for="page-search" class="sr-only">Search pages</label>
                                <input
                                    id="page-search"
                                    type="search"
                                    name="search"
                                    value="{{ $search ?? '' }}"
                                    placeholder="Search pages..."
                                    class="w-64 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                >
                                <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                                    Search
                                </button>
                                @if (($search ?? '') !== '')
                                    <a href="{{ route('admin.pages.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                        Clear
                                    </a>
                                @endif
                            </form>
                            <span class="text-sm text-gray-500">{{ $pages->count() }} pages</span>
                        </div>
                    </div>
                </div>

                @if ($pages->isEmpty())
                    <div class="px-6 py-8 text-sm text-gray-600">
                        @if (($search ?? '') !== '')
                            No pages matched your search.
                        @else
                            No pages created yet.
                        @endif
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="w-12 px-6 py-3">
                                        <input
                                            type="checkbox"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            :checked="allSelected"
                                            @change="toggleAll()"
                                            aria-label="Select all pages"
                                        >
                                    </th>
                                    <th class="px-6 py-3">Title</th>
                                    <th class="px-6 py-3">Type</th>
                                    <th class="px-6 py-3">Slug</th>
                                    <th class="px-6 py-3">Status</th>
                                    <th class="px-6 py-3">Updated</th>
                                    <th class="px-6 py-3 text-right">Actions</th>
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
                                                aria-label="Select {{ $page->title }}"
                                            >
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">{{ $page->title }}</div>
                                            @if ($page->seo_title)
                                                <div class="mt-1 text-xs text-gray-500">{{ $page->seo_title }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                {{ ($page->content_type ?? 'page') === 'page' ? 'Body / Page' : ucfirst($page->content_type) }}
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
                                                {{ ucfirst($page->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">{{ $page->updated_at }}</td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-end gap-2">
                                                @if (! $isPublicPage || $isPublished)
                                                    <a href="{{ $viewUrl }}" target="_blank" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                        View
                                                    </a>
                                                @endif
                                                <a href="{{ $previewUrl }}" target="_blank" class="rounded-md border border-blue-200 bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">
                                                    Preview
                                                </a>
                                                <a href="{{ route('admin.pages.edit', $page->id) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                    Edit
                                                </a>
                                                <form method="POST" action="{{ route('admin.pages.destroy', $page->id) }}" onsubmit="return confirm('Delete this page?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">
                                                        Delete
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
            </section>
        </div>
    </div>
</x-app-layout>
