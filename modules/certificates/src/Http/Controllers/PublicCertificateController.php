<?php

namespace Modules\Certificates\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Certificates\Models\Certificate;
use Modules\Certificates\Models\CertificateTemplate;
use Modules\Certificates\Services\CertificateTemplateRenderer;
use Modules\Certificates\Services\CertificateTemplateSettings;
use Modules\Certificates\Support\CertificateNameNormalizer;
use Symfony\Component\HttpFoundation\Response;

class PublicCertificateController extends Controller
{
    public function search(): Response
    {
        $template = app(CertificateTemplateSettings::class)->templateFor('search');

        if ($template) {
            $rendered = app(CertificateTemplateRenderer::class)->render($template, $this->searchValues());

            return response()->view('certificates::public.template', [
                'renderedTemplate' => $rendered,
                'pageTitle' => __('certificates::public.title'),
            ]);
        }

        return response()->view('certificates::public.search');
    }

    public function lookup(Request $request): Response
    {
        // Compare the submitted text itself, before global whitespace trimming.
        $rawSlug = $request->input('slug');
        $rawName = $request->input('name');
        if ($request->getContent() !== '') {
            if ($request->isJson()) {
                $raw = json_decode($request->getContent(), true);
            } else {
                parse_str($request->getContent(), $raw);
            }
            if (is_array($raw)) {
                $rawSlug = $raw['slug'] ?? $rawSlug;
                $rawName = $raw['name'] ?? $rawName;
            }
        }

        // Exact certificate-number lookup keeps precedence and is case/zero sensitive.
        if (is_string($rawSlug) && $rawSlug !== '') {
            $certificate = strlen($rawSlug) <= 760 ? Certificate::findExact($rawSlug) : null;

            return $certificate ? $this->grantAndRedirect($request, $certificate) : $this->notFound();
        }

        $normalized = CertificateNameNormalizer::normalize(is_string($rawName) ? $rawName : '');
        if ($normalized === '') {
            return $this->notFound(
                __('certificates::message.missing_title'),
                __('certificates::message.missing'),
            );
        }

        $matches = Certificate::query()->where('name_normalized', $normalized)->get();

        if ($matches->count() === 0) {
            return $this->notFound(
                __('certificates::message.not_found_title'),
                __('certificates::message.not_found'),
            );
        }

        if ($matches->count() > 1) {
            return response()->view('certificates::public.message', [
                'title' => __('certificates::message.ambiguous_title'),
                'message' => __('certificates::message.ambiguous'),
            ], 409);
        }

        return $this->grantAndRedirect($request, $matches->first());
    }

    public function show(string $certificateSlug): Response
    {
        $certificate = Certificate::findExact($certificateSlug);
        if (! $certificate) {
            return $this->notFound();
        }

        $imageUrl = URL::temporarySignedRoute('certificates.image', now()->addMinutes(10), ['slug' => $certificate->slug]);
        $template = app(CertificateTemplateSettings::class)->templateFor('show');

        if ($template) {
            $rendered = app(CertificateTemplateRenderer::class)->render($template, [
                'page_title' => $certificate->name,
                'name' => $certificate->name,
                'slug' => $certificate->slug,
                'date' => $certificate->date?->format('Y-m-d'),
                'date_iso' => $certificate->date?->toDateString(),
                'image_url' => $imageUrl,
                'image_tag' => '<img src="'.e($imageUrl).'" alt="'.e(__('certificates::public.image_alt', ['name' => $certificate->name])).'" class="certificate-original" decoding="async">',
                'number_label' => __('certificates::public.number_field'),
                'date_label' => __('certificates::public.date_field'),
                'search_url' => route('certificates.search'),
                'search_label' => __('certificates::public.title'),
                'home_url' => url('/'),
            ]);

            return response()->view('certificates::public.template', [
                'renderedTemplate' => $rendered,
                'pageTitle' => $certificate->name,
            ]);
        }

        return response()->view('certificates::public.show', [
            'certificate' => $certificate,
            'imageUrl' => $imageUrl,
        ]);
    }

    public function image(Request $request, string $slug): Response
    {
        if (! $request->hasValidSignature()) {
            return response(__('certificates::message.invalid_link'), 403);
        }

        $certificate = Certificate::findExact($slug);
        if (! $certificate || ! str_starts_with($certificate->image, 'certificates/images/') || ! Storage::disk('local')->exists($certificate->image)) {
            return $this->notFound();
        }

        return Storage::disk('local')->response($certificate->image, basename($certificate->image), ['X-Content-Type-Options' => 'nosniff'], 'inline');
    }

    public function styles(): Response
    {
        return response(file_get_contents(__DIR__.'/../../../resources/styles/public.css'), 200, ['Content-Type' => 'text/css; charset=UTF-8']);
    }

    public function templateScript(string $slug): Response
    {
        $template = CertificateTemplate::query()->active()->where('slug', $slug)->firstOrFail();

        return response((string) $template->js_code, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function asset(string $asset): Response
    {
        $assets = [
            'cairo-arabic.woff2' => ['fonts/cairo-arabic.woff2', 'font/woff2'],
            'cairo-latin.woff2' => ['fonts/cairo-latin.woff2', 'font/woff2'],
            'certificate.svg' => ['icons/certificate.svg', 'image/svg+xml'],
        ];
        abort_unless(isset($assets[$asset]), 404);
        [$path, $mime] = $assets[$asset];

        return response(file_get_contents(__DIR__.'/../../../resources/assets/'.$path), 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function searchValues(): array
    {
        return [
            'heading' => __('certificates::public.title'),
            'intro' => __('certificates::public.intro'),
            'home_url' => url('/'),
            'home_label' => __('certificates::public.home_link'),
            'lookup_url' => route('certificates.lookup'),
            'csrf_field' => csrf_field(),
            'number_label' => __('certificates::public.number_label'),
            'number_help' => __('certificates::public.number_help'),
            'or_label' => __('certificates::public.or'),
            'name_label' => __('certificates::public.name_label'),
            'submit_label' => __('certificates::public.submit'),
            'search_form' => $this->searchFormHtml(),
        ];
    }

    private function searchFormHtml(): string
    {
        return '<form method="POST" action="'.e(route('certificates.lookup')).'" class="certificate-search">'
            .csrf_field()
            .'<label for="certificate-number">'.e(__('certificates::public.number_label')).'</label>'
            .'<input type="text" id="certificate-number" name="slug" maxlength="190" autocomplete="off" autocapitalize="none" spellcheck="false" dir="ltr" aria-describedby="certificate-help">'
            .'<p id="certificate-help" class="certificate-help">'.e(__('certificates::public.number_help')).'</p>'
            .'<span class="certificate-search__or certificate-search__divider">'.e(__('certificates::public.or')).'</span>'
            .'<label for="certificate-name">'.e(__('certificates::public.name_label')).'</label>'
            .'<input type="text" id="certificate-name" name="name" maxlength="255" autocomplete="off" dir="auto">'
            .'<button type="submit">'.e(__('certificates::public.submit')).'</button>'
            .'</form>';
    }

    private function grantAndRedirect(Request $request, Certificate $certificate): Response
    {
        $nonce = bin2hex(random_bytes(24));
        Cache::put('certificates:view-grant:'.$nonce, [
            'slug' => $certificate->slug,
            'ip' => hash('sha256', (string) $request->ip()),
        ], 15);
        $request->session()->put('certificates.view_grant', $nonce);

        return redirect()->to($certificate->publicUrl(), 303);
    }

    private function notFound(?string $title = null, ?string $message = null): Response
    {
        return response()->view('certificates::public.message', [
            'title' => $title ?? __('certificates::message.not_found_title'),
            'message' => $message ?? __('certificates::message.default_not_found'),
        ], 404);
    }
}
