<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('lms::ui.auto_75ba717503') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-6 sm:px-8 lg:px-12">
            <section class="rounded-lg border border-gray-200 bg-white p-8 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">LMS</p>
                <h1 class="mt-2 text-2xl font-semibold text-gray-950">{{ $title }}</h1>
            </section>
        </div>
    </div>
</x-app-layout>
