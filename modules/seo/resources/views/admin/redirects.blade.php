<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('seo::ui.redirects') }}</h2>
            <a href="{{ route('admin.plugins.seo.redirects.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('seo::ui.add_redirect') }}</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('admin.plugins.seo.redirects.index') }}" class="flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <div class="min-w-64 flex-1">
                    <label for="redirect-search" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.search') }}</label>
                    <input id="redirect-search" name="search" value="{{ $search }}" placeholder="{{ __('seo::ui.redirect_search_placeholder') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <button type="submit" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.search') }}</button>
            </form>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                @if ($redirects === null)
                    <p class="px-4 py-6 text-sm text-gray-500">{{ __('seo::ui.no_records') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.source_url') }}</th>
                                    <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.target_url') }}</th>
                                    <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.redirect_type') }}</th>
                                    <th class="px-4 py-3 text-start font-semibold text-gray-600">{{ __('seo::ui.status') }}</th>
                                    <th class="px-4 py-3 text-end font-semibold text-gray-600">{{ __('seo::ui.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($redirects as $redirect)
                                    <tr>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-800">{{ $redirect->from_path }}</td>
                                        <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                            @if ((int) $redirect->status_code === 410)
                                                <span class="text-gray-500">{{ __('seo::ui.gone') }}</span>
                                            @else
                                                {{ $redirect->to_path }}
                                                @if ($redirect->is_external)
                                                    <span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800">{{ __('seo::ui.external') }}</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-gray-700">
                                            @switch((int) $redirect->status_code)
                                                @case(301) 301 — {{ __('seo::ui.permanent') }} @break
                                                @case(302) 302 — {{ __('seo::ui.temporary') }} @break
                                                @case(410) 410 — {{ __('seo::ui.gone') }} @break
                                                @default {{ $redirect->status_code }}
                                            @endswitch
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $redirect->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                                {{ $redirect->is_active ? __('seo::ui.active') : __('seo::ui.inactive') }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-end">
                                            <div class="flex flex-wrap justify-end gap-3">
                                                <form method="POST" action="{{ route('admin.plugins.seo.redirects.toggle', $redirect) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="font-semibold text-gray-700 hover:text-gray-950">{{ $redirect->is_active ? __('seo::ui.disable') : __('seo::ui.enable') }}</button>
                                                </form>
                                                <a href="{{ route('admin.plugins.seo.redirects.edit', $redirect) }}" class="font-semibold text-gray-700 hover:text-gray-950">{{ __('seo::ui.edit') }}</a>
                                                <form method="POST" action="{{ route('admin.plugins.seo.redirects.destroy', $redirect) }}" onsubmit="return confirm('{{ __('seo::ui.redirect_delete_confirm') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="font-semibold text-red-600 hover:text-red-800">{{ __('seo::ui.delete') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('seo::ui.no_records') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-gray-200 px-4 py-3">{{ $redirects->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
