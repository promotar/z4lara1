<?php

namespace App\Http\Controllers;

use App\Platform\Core\Localization\PlatformLocale;
use App\Platform\Core\Seo\LocaleUrlStrategy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LocaleController extends Controller
{
    /**
     * Page language for the current visitor. This is a temporary preference
     * (session) — it never changes the site default, which only a super admin
     * can set from Settings (general.site_language).
     */
    public function __invoke(
        Request $request,
        PlatformLocale $locale,
        LocaleUrlStrategy $localeUrls,
        string $target,
    ): RedirectResponse {
        abort_unless($locale->isAvailable($target), 404);

        $locale->setSite($target);

        // Explicit destination (used by "View public post" in the admin editor so
        // the preview opens in the tab's language). Only same-site relative
        // paths are accepted to avoid open redirects.
        $to = trim((string) $request->query('to'));

        if ($to !== '' && str_starts_with($to, '/') && ! str_starts_with($to, '//')) {
            return redirect()->to($to);
        }

        $referer = trim((string) $request->headers->get('referer'));
        $url = $referer !== '' ? $referer : route('front.home');

        return redirect()->to($localeUrls->withLocale($url, $target));
    }

    /**
     * Panel language — every signed-in user controls the language of their own
     * dashboard/account panel. Stored on the user record so it persists.
     */
    public function updatePanel(Request $request, PlatformLocale $locale): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', 'max:10'],
        ]);

        abort_unless($locale->isAvailable($validated['locale']), 404);

        $user = $request->user();
        abort_unless($user !== null, 403);

        $user->forceFill(['locale' => $validated['locale']])->save();
        $locale->set($validated['locale']);

        return back();
    }
}