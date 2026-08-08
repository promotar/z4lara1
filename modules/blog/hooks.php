<?php

use App\Platform\Core\Hooks\HookManager;
use Modules\Blog\Services\PageBuilderExtension;

return static function (HookManager $hooks): void {
    $extension = app(PageBuilderExtension::class);

    /*
     * BLOG -> PAGE BUILDER INTEGRATION
     * --------------------------------
     * These callbacks use the documented Page Builder extension contract. Keeping registration
     * in Blog's hook file is intentional: the platform loads this file only while Blog is active,
     * so the VvvebJs element and its frontend renderer disappear automatically when Blog is
     * disabled or uninstalled. No Blog-specific code belongs in the Page Builder module.
     */
    $hooks->addFilter(
        'plugin.page-builder.editor.extensions',
        [$extension, 'editorExtensions'],
        priority: 10,
        acceptedArgs: 2,
    );
    $hooks->addFilter(
        'plugin.page-builder.frontend.html',
        [$extension, 'renderFrontendHtml'],
        priority: 10,
        acceptedArgs: 2,
    );
};
