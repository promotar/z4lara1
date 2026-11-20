<?php

namespace Modules\Certificates\Services;

use Modules\Certificates\Models\CertificateTemplate;

final class CertificateTemplateRenderer
{
    /**
     * Render a saved certificate template. Plain {{token}} values are escaped;
     * {{{token}}} values are injected raw (server-built markup only).
     *
     * @param  array<string, scalar|null>  $values
     */
    public function render(CertificateTemplate $template, array $values): string
    {
        $markup = (string) $template->html_code;

        $markup = preg_replace_callback(
            '/\{\{\{\s*([a-z0-9_]+)\s*\}\}\}/i',
            fn (array $match): string => (string) ($values[$match[1]] ?? ''),
            $markup,
        ) ?? $markup;

        $markup = preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/i',
            fn (array $match): string => array_key_exists($match[1], $values) ? e((string) $values[$match[1]]) : $match[0],
            $markup,
        ) ?? $markup;

        $slug = e($template->slug);
        $output = '<div data-certificate-template="'.$slug.'">'
            .'<style data-certificate-template-style="'.$slug.'">'.(string) $template->css_code.'</style>'
            .$markup
            .'</div>';

        if (trim((string) $template->js_code) !== '') {
            $output .= '<script src="'.e(route('certificates.template-script', ['slug' => $template->slug], false)).'" defer data-certificate-template-script="'.$slug.'"></script>';
        }

        return $output;
    }
}
