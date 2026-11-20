<?php

namespace Modules\Seo\Services;

use App\Platform\Core\Seo\SeoContributor;
use App\Platform\Core\Seo\SeoData;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Seo\Models\SeoSchemaTemplate;
use Throwable;

/**
 * Applies active admin-managed schema templates to matching routes.
 */
final class SchemaTemplateContributor implements SeoContributor
{
    public function __construct(private readonly SeoSiteResolver $sites)
    {
    }

    public function module(): string
    {
        return 'seo';
    }

    public function supports(Request $request): bool
    {
        return true;
    }

    public function contribute(SeoData $seo, Request $request): void
    {
        try {
            $siteId = $this->sites->id();
            $routeName = (string) ($request->route()?->getName() ?? '');
            $path = '/'.ltrim($request->path(), '/');
            $relative = ltrim($path, '/');

            $templates = SeoSchemaTemplate::query()
                ->where('site_id', $siteId)
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();

            foreach ($templates as $template) {
                $module = trim((string) $template->module_slug);

                if ($module !== '' && $routeName !== '') {
                    if (! Str::startsWith($routeName, $module.'.') && ! Str::startsWith($relative, trim($module, '/').'/')) {
                        continue;
                    }
                }

                $pattern = trim((string) $template->route_pattern);

                if ($pattern !== '' && $routeName !== '' && ! Str::is($pattern, $routeName)) {
                    if (! Str::is($pattern, $path) && ! Str::is($pattern, $relative)) {
                        continue;
                    }
                }

                $schema = json_decode((string) $template->schema_json, true);

                if (! is_array($schema) || $schema === [] || array_is_list($schema)) {
                    continue;
                }

                $hash = hash('sha256', json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
                $seen = (array) ($seo->extra['schema_hashes'] ?? []);

                if (isset($seen[$hash])) {
                    continue;
                }

                $seen[$hash] = true;
                $seo->extra['schema_hashes'] = $seen;
                $seo->schema[] = $schema;
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
