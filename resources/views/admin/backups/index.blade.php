<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight" data-i18n="core.backups.title">{{ __('core.backups.title') }}</h2>
                <p class="mt-1 text-sm text-gray-500" data-i18n="core.backups.subtitle">{{ __('core.backups.subtitle') }}</p>
            </div>
            <form method="POST" action="{{ route('admin.backups.store') }}">
                @csrf
                <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800" data-i18n="core.backups.create">
                    {{ __('core.backups.create') }}
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('status') }}</div>
            @endif

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-6 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900" data-i18n="core.backups.checkpoints">{{ __('core.backups.checkpoints') }}</h3>
                            <p class="mt-1 text-sm text-gray-500" data-i18n="core.backups.hint">{{ __('core.backups.hint') }}</p>
                        </div>
                        <span class="text-sm text-gray-500">{{ count($backups) }} latest backups</span>
                    </div>
                </div>

                @if ($backups === [])
                    <div class="px-6 py-10 text-center text-sm text-gray-600" data-i18n="core.backups.empty">{{ __('core.backups.empty') }}</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-6 py-3" data-i18n="core.backups.title">{{ __('core.backups.title') }}</th>
                                    <th class="px-6 py-3" data-i18n="core.backups.type">{{ __('core.backups.type') }}</th>
                                    <th class="px-6 py-3" data-i18n="core.backups.status">{{ __('core.backups.status') }}</th>
                                    <th class="px-6 py-3" data-i18n="core.backups.taken_at">{{ __('core.backups.taken_at') }}</th>
                                    <th class="px-6 py-3" data-i18n="core.backups.file">{{ __('core.backups.file') }}</th>
                                    <th class="px-6 py-3 text-right" data-i18n="core.backups.actions">{{ __('core.backups.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($backups as $backup)
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="font-semibold text-gray-900">#{{ $backup['id'] }} {{ $backup['description'] }}</div>
                                            @if ($backup['notes'])
                                                <div class="mt-1 max-w-xl truncate text-xs text-gray-500">{{ $backup['notes'] }}</div>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 text-gray-600">{{ $backup['checkpoint_label'] }}</td>
                                        <td class="px-6 py-4">
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                                'bg-green-100 text-green-700' => $backup['status'] === 'completed',
                                                'bg-yellow-100 text-yellow-800' => $backup['status'] === 'pending',
                                                'bg-red-100 text-red-700' => $backup['status'] === 'failed',
                                            ])>
                                                {{ ucfirst($backup['status']) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-gray-500">{{ $backup['created_at'] }}</td>
                                        <td class="px-6 py-4">
                                            @if ($backup['file_exists'])
                                                <span class="text-xs font-semibold text-green-700" data-i18n="core.backups.exists">{{ __('core.backups.exists') }}</span>
                                            @else
                                                <span class="text-xs font-semibold text-red-700" data-i18n="core.backups.missing">{{ __('core.backups.missing') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="flex flex-wrap items-center justify-end gap-2">
                                                <a href="{{ route('admin.backups.location', $backup['id']) }}" target="_blank" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" data-i18n="core.backups.location">
                                                    {{ __('core.backups.location') }}
                                                </a>
                                                <form method="POST" action="{{ route('admin.backups.restore', $backup['id']) }}" onsubmit="return confirm('Record restore confirmation for this backup?');">
                                                    @csrf
                                                    <button class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50" data-i18n="core.backups.restore">
                                                        {{ __('core.backups.restore') }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.backups.destroy', $backup['id']) }}" onsubmit="return confirm('Remove this backup checkpoint?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50" data-i18n="core.backups.remove">
                                                        {{ __('core.backups.remove') }}
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
