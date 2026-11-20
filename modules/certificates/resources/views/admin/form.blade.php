<x-app-layout>
    @include('certificates::admin.styles')
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800" dir="ltr">{{ $certificate->exists ? 'Edit Certificate' : 'Add Certificate' }}</h2></x-slot>
    <div class="py-8" dir="ltr">
        <div class="mx-auto max-w-3xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('status'))<p role="status" class="rounded-md bg-green-50 p-4 text-green-800">{{ session('status') }}</p>@endif
            @if ($errors->any())
                <div role="alert" class="rounded-md bg-red-50 p-4 text-red-800"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif
            <form method="POST" enctype="multipart/form-data" action="{{ $certificate->exists ? route('admin.plugins.certificates.update', $certificate) : route('admin.plugins.certificates.store') }}" class="space-y-5 rounded-xl border border-gray-200 bg-white p-6">
                @csrf
                @if ($certificate->exists) @method('PUT') @endif
                <div><label for="certificate-name" class="mb-2 block font-semibold">{{ __('certificates::ui.name') }}</label><input id="certificate-name" name="name" required maxlength="255" value="{{ old('name', $certificate->name) }}" class="w-full rounded-md border-gray-300"></div>
                <div><label for="certificate-date" class="mb-2 block font-semibold">{{ __('certificates::ui.issue_date') }}</label><input id="certificate-date" type="date" name="date" required value="{{ old('date', $certificate->date?->toDateString() ?? now()->toDateString()) }}" class="w-full rounded-md border-gray-300"></div>
                <div><label for="certificate-slug" class="mb-2 block font-semibold">{{ __('certificates::ui.certificate_number') }}</label><input id="certificate-slug" name="slug" required maxlength="190" value="{{ old('slug', $certificate->slug) }}" autocomplete="off" class="w-full rounded-md border-gray-300" dir="ltr"></div>
                <div>
                    <label for="certificate-image" class="mb-2 block font-semibold">{{ __('certificates::ui.image') }}</label>
                    <input id="certificate-image" type="file" name="image" accept="image/jpeg,image/png,image/webp" @required(! $certificate->exists) class="w-full rounded-md border border-gray-300 p-2">
                    @if ($certificate->exists)
                        <img class="mt-4 h-auto w-full rounded-md border border-gray-200" src="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('certificates.image', now()->addMinutes(10), ['slug' => $certificate->slug]) }}" alt="{{ __('certificates::ui.certificate_image_alt', ['name' => $certificate->name]) }}">
                    @endif
                </div>
                <div class="flex flex-wrap gap-3"><button type="submit" class="certificates-action rounded-md bg-gray-900 px-5 py-2 text-white">{{ __('certificates::ui.save') }}</button><a href="{{ route('admin.plugins.certificates.index') }}" class="rounded-md border border-gray-300 px-5 py-2">{{ __('certificates::ui.back_to_certificates') }}</a></div>
            </form>
        </div>
    </div>
</x-app-layout>
