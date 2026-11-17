@php
    $isModule = isset($module) && $module !== null && $module !== '';
    $moduleLabel = $moduleLabel ?? $module;
    $heading = $isModule
        ? __('core.translations.add_module_language', ['module' => $moduleLabel])
        : __('core.translations.add_language');
@endphp

<div class="mt-6 rounded-md border border-gray-200 bg-gray-50 p-6">
    <h3 class="text-lg font-semibold text-gray-900">{{ $heading }}</h3>
    <p class="mt-1 text-sm text-gray-600">{{ __('core.translations.language_hint') }}</p>

    <form method="POST" action="{{ route('admin.translations.languages.store') }}" enctype="multipart/form-data" class="mt-5 max-w-xl space-y-4">
        @csrf
        @if ($isModule)
            <input type="hidden" name="module" value="{{ $module }}">
        @endif

        <label class="block text-sm">
            <span class="mb-1 block font-semibold text-gray-700">{{ __('core.translations.code') }}</span>
            <input name="code" required maxlength="16" placeholder="es" class="block w-full rounded-md border-gray-300 text-sm">
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-semibold text-gray-700">{{ __('core.translations.label') }}</span>
            <input name="label" required maxlength="64" placeholder="{{ __('core.translations.language_example') }}" class="block w-full rounded-md border-gray-300 text-sm">
        </label>

        <label class="block text-sm">
            <span class="mb-1 block font-semibold text-gray-700">{{ __('core.translations.upload_file') }}</span>
            <input type="file" name="file" accept=".txt,text/plain" required class="block w-full rounded-md border-gray-300 text-sm">
        </label>

        <div>
            <button type="submit" class="tr-btn" style="background:#111827 !important;color:#ffffff !important;border:1px solid #111827 !important;text-decoration:none !important">{{ __('core.translations.create_language') }}</button>
        </div>
    </form>
</div>
