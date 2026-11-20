<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('seo::ui.schemas') }}</h2>
            <a href="{{ route('admin.plugins.seo.schemas.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('seo::ui.add_schema') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @if ($templates === null)
                    <p class="px-4 py-6 text-sm text-gray-500">{{ __('seo::ui.no_records') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.name') }}</th>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.type') }}</th>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.route_pattern') }}</th>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.status') }}</th>
                                <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.priority') }}</th>
                                <th class="px-4 py-3 text-end font-semibold text-gray-600">{{ __('seo::ui.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($templates as $template)
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $template->name }}</div>
                                        <div class="font-mono text-xs text-gray-500">{{ $template->key }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $template->type ?: '—' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $template->route_pattern ?: '*' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $template->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $template->is_active ? __('seo::ui.active') : __('seo::ui.inactive') }}</span>
                                    </td>
                                    <td class="px-4 py-3">{{ $template->priority }}</td>
                                    <td class="px-4 py-3 text-end">
                                        <div class="flex justify-end gap-3">
                                            <a href="{{ route('admin.plugins.seo.schemas.edit', $template) }}" class="font-semibold text-gray-700 hover:text-gray-950">{{ __('seo::ui.edit') }}</a>
                                            <form method="POST" action="{{ route('admin.plugins.seo.schemas.destroy', $template) }}" onsubmit="return confirm('{{ __('seo::ui.schema_delete_confirm') }}');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="font-semibold text-red-600 hover:text-red-800">{{ __('seo::ui.delete') }}</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ __('seo::ui.no_records') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="border-t border-gray-200 px-4 py-3">{{ $templates->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
