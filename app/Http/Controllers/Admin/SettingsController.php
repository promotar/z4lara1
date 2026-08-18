<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Platform\Core\Services\PlatformMailConfigurator;
use App\Platform\Core\Services\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

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

    public function update(Request $request, SettingsRepository $settings, PlatformMailConfigurator $mail): RedirectResponse
    {
        $request->validate([
            'settings' => ['required', 'array'],
            'files' => ['nullable', 'array'],
            'files.*.*' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,ico', 'max:2048'],
            'media' => ['nullable', 'array'],
            'remove_files' => ['nullable', 'array'],
            'settings.general.email_verification_required' => ['nullable', 'boolean'],
            'settings.mail.smtp_enabled' => ['nullable', 'boolean'],
            'settings.mail.smtp_host' => ['nullable', 'required_if:settings.mail.smtp_enabled,1', 'string', 'max:255'],
            'settings.mail.smtp_port' => ['nullable', 'required_if:settings.mail.smtp_enabled,1', 'integer', 'min:1', 'max:65535'],
            'settings.mail.smtp_encryption' => ['nullable', 'required_if:settings.mail.smtp_enabled,1', 'in:tls,smtps,none'],
            'settings.mail.smtp_username' => ['nullable', 'string', 'max:255'],
            'settings.mail.smtp_password' => ['nullable', 'string', 'max:4000'],
            'settings.mail.smtp_timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'settings.mail.from_address' => ['nullable', 'required_if:settings.mail.smtp_enabled,1', 'email', 'max:255'],
            'settings.mail.from_name' => ['nullable', 'required_if:settings.mail.smtp_enabled,1', 'string', 'max:255'],
        ]);

        $settings->update(
            $request->input('settings', []),
            $request->file('files', []),
            $request->input('remove_files', []),
            $request->input('media', []),
            $request->user()?->id,
            'admin.settings',
        );
        $mail->apply();

        return back()->with('status', $this->message($settings, 'تم حفظ الإعدادات بنجاح.', 'Settings saved successfully.'));
    }

    public function testMail(Request $request, SettingsRepository $settings, PlatformMailConfigurator $mail): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);
        $values = $settings->values();
        if (! filter_var($values['mail.smtp_enabled'] ?? false, FILTER_VALIDATE_BOOL)) {
            return back()->withErrors(['test_email' => 'Enable and save SMTP before sending a test message.'])->withFragment('settings-mail');
        }

        try {
            $mail->apply();
            Mail::raw('This is a test message from the Art INPA platform SMTP settings.', function ($message) use ($validated): void {
                $message->to($validated['test_email'])->subject('Art INPA SMTP test');
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['test_email' => 'SMTP test failed: '.$exception->getMessage()])->withFragment('settings-mail');
        }

        return back()->with('status', 'SMTP test email sent successfully.')->withFragment('settings-mail');
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
