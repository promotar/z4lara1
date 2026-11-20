<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('seo::ui.dashboard') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.indexable_content') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $counts['indexable'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.active_redirects') }}</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $counts['active_redirects'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.seo_issues') }}</p>
                    <p class="mt-2 text-3xl font-bold {{ $counts['open_issues'] > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $counts['open_issues'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm text-gray-500">{{ __('seo::ui.sitemap_status') }}</p>
                    @if ($sitemap['enabled'])
                        <p class="mt-2 text-xl font-bold text-gray-900">{{ __('seo::ui.sitemap_status_'.$sitemap['status']) }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.sitemap_urls_count', ['count' => $sitemap['urls']]) }}</p>
                    @else
                        <p class="mt-2 text-xl font-bold text-gray-500">{{ __('seo::ui.sitemap_disabled') }}</p>
                    @endif
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ __('seo::ui.issues_summary') }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.issues_summary_help') }}</p>
                        </div>
                        <a href="{{ route('admin.plugins.seo.audit.index') }}" class="text-sm font-semibold text-gray-700 underline">{{ __('seo::ui.view_audit') }}</a>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-md border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-100 text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($issueSummary as $issue)
                                    <tr>
                                        <td class="px-3 py-3 text-gray-700">{{ __('seo::ui.audit_types.'.$issue->type) }}</td>
                                        <td class="px-3 py-3 text-end font-semibold text-gray-900">{{ $issue->total }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="px-3 py-6 text-center text-gray-500">{{ __('seo::ui.no_issues') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">{{ __('seo::ui.quick_access') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('seo::ui.quick_access_help') }}</p>
                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <a href="{{ route('admin.plugins.seo.settings.edit') }}" class="rounded-md border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.settings') }}</a>
                        <a href="{{ route('admin.plugins.seo.sitemap.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.sitemap') }}</a>
                        <a href="{{ route('admin.plugins.seo.redirects.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.redirects') }}</a>
                    </div>
                    @if ($sitemap['enabled'])
                        <div class="mt-5 border-t border-gray-100 pt-4 text-sm">
                            <a href="{{ url('/sitemap.xml') }}" target="_blank" rel="noopener" class="font-semibold text-gray-800 underline">{{ url('/sitemap.xml') }}</a>
                            @if ($sitemap['last_generated'])
                                <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.last_generated') }}: {{ $sitemap['last_generated']->diffForHumans() }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
