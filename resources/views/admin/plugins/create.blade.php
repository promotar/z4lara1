<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Install Plugin</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.plugins.store') }}" enctype="multipart/form-data" class="bg-white p-6 shadow-sm sm:rounded-lg space-y-5">
                @csrf
                <div>
                    <x-input-label for="plugin_zip" value="Plugin ZIP file" />
                    <input id="plugin_zip" name="plugin_zip" type="file" accept=".zip" required class="mt-2 block w-full border-gray-300 rounded-md" />
                    <x-input-error :messages="$errors->get('plugin_zip')" class="mt-2" />
                </div>

                <div class="text-sm text-gray-600">
                    ZIP must include module.json and ServiceProvider.php. The system rejects unsafe paths and executable files.
                    If the slug is already installed and the name/owner match, the platform will show a review page before updating.
                </div>

                <x-primary-button>Upload and Install</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
