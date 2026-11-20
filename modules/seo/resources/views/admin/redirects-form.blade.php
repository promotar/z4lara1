<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $redirect->exists ? __('seo::ui.edit_redirect') : __('seo::ui.add_redirect') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc space-y-1 ps-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ $redirect->exists ? route('admin.plugins.seo.redirects.update', $redirect) : route('admin.plugins.seo.redirects.store') }}" class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                @if ($redirect->exists)
                    @method('PATCH')
                @endif

                <div>
                    <label for="redirect-from" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.source_url') }}</label>
                    <input id="redirect-from" name="from_path" value="{{ old('from_path', $redirect->from_path) }}" placeholder="/old-page" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm" required>
                    <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.redirect_from_help') }}</p>
                </div>

                <div>
                    <label for="redirect-to" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.target_url') }}</label>
                    <input id="redirect-to" name="to_path" value="{{ old('to_path', $redirect->to_path) }}" placeholder="/new-page" class="mt-1 block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm">
                    <p class="mt-1 text-xs text-gray-500">{{ __('seo::ui.redirect_to_help') }}</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="redirect-status" class="block text-sm font-medium text-gray-700">{{ __('seo::ui.redirect_type') }}</label>
                        <select id="redirect-status" name="status_code" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            @php
                                $statuses = [301, 302, 410];
                                $currentStatus = (int) old('status_code', $redirect->status_code);
                                if ($redirect->exists && in_array($currentStatus, [307, 308], true)) {
                                    $statuses[] = $currentStatus;
                                }
                            @endphp
                            @foreach ($statuses as $status)
                                <option value="{{ $status }}" @selected($currentStatus === $status)>
                                    {{ $status }} — {{ $status === 301 ? __('seo::ui.permanent') : ($status === 302 ? __('seo::ui.temporary') : __('seo::ui.gone')) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-gray-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $redirect->is_active ?? true))>
                        {{ __('seo::ui.active') }}
                    </label>
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('seo::ui.save') }}</button>
                    <a href="{{ route('admin.plugins.seo.redirects.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('seo::ui.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
