<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(SettingsRepository $settings): View
    {
        return view('admin.settings.index', [
            'groups' => $settings->all(),
            'settingsPath' => $settings->path(),
            'mediaLibrary' => $settings->mediaLibrary(),
        ]);
    }

    public function update(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'files' => ['nullable', 'array'],
            'files.*.*' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,ico', 'max:2048'],
            'media' => ['nullable', 'array'],
            'remove_files' => ['nullable', 'array'],
        ]);

        $settings->update(
            $request->input('settings', []),
            $request->file('files', []),
            $request->input('remove_files', []),
            $request->input('media', []),
            $request->user()?->id,
            'admin.settings',
        );

        return back()->with('status', $this->message($settings, 'تم حفظ الإعدادات بنجاح.', 'Settings saved successfully.'));
    }

    public function updateMedia(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $validated = $request->validate([
            'media_url' => ['required', 'string'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings->updateMediaMetadata($validated['media_url'], $validated);

        return back()->with('status', $this->message($settings, 'تم حفظ بيانات السيو للصورة بنجاح.', 'Image SEO data saved successfully.'));
    }

    private function message(SettingsRepository $settings, string $arabic, string $english): string
    {
        return ($settings->values()['general.site_language'] ?? 'en') === 'ar' ? $arabic : $english;
    }
}
