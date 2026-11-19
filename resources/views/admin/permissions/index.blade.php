<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl text-gray-800 leading-tight" data-i18n="core.permissions.title">{{ __('core.permissions.title') }}</h2></x-slot>
    <div class="py-8"><div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @if (session('status')) <div class="p-4 bg-green-100 text-green-800 rounded-md">{{ session('status') }}</div> @endif
        <div class="grid md:grid-cols-2 gap-6">
            <form method="POST" action="{{ route('admin.permissions.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                @csrf
                <div>
                    <x-input-label for="name" value="{{ __('core.permissions.name') }}" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" placeholder="plugins.install" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <x-primary-button data-i18n="core.permissions.create">{{ __('core.permissions.create') }}</x-primary-button>
            </form>

            <form method="POST" action="{{ route('admin.permissions.sync-defaults') }}" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-4">
                @csrf
                <div>
                    <h3 class="font-semibold text-gray-900" data-i18n="core.permissions.sync_title">{{ __('core.permissions.sync_title') }}</h3>
                    <p class="mt-2 text-sm text-gray-600" data-i18n="core.permissions.sync_hint">{{ __('core.permissions.sync_hint') }}</p>
                </div>
                <x-primary-button data-i18n="core.permissions.sync">{{ __('core.permissions.sync') }}</x-primary-button>
            </form>
        </div>

        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 class="font-semibold text-gray-900" data-i18n="core.permissions.default_title">{{ __('core.permissions.default_title') }}</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-700">
                        <tr>
                            <th class="p-3" data-i18n="core.permissions.permission">{{ __('core.permissions.permission') }}</th>
                            <th class="p-3" data-i18n="core.permissions.description">{{ __('core.permissions.description') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($defaultPermissions as $name => $description)
                            <tr class="border-t">
                                <td class="p-3 font-mono text-xs text-gray-900">{{ $name }}</td>
                                <td class="p-3 text-gray-700">{{ $description }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 class="font-semibold text-gray-900" data-i18n="core.permissions.current_title">{{ __('core.permissions.current_title') }}</h3>
            <div class="flex flex-wrap gap-2">@foreach ($permissions as $permission)<span class="px-3 py-1 bg-gray-100 rounded">{{ $permission->name }}</span>@endforeach</div>
        </div>
    </div></div>
</x-app-layout>
