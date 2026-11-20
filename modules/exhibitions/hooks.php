<?php

use App\Platform\Core\Hooks\HookManager;
use Modules\Exhibition\Services\PageBuilderExtension;
use Modules\Exhibition\Services\VirtualExhibitionsPageBuilderExtension;

return static function (HookManager $hooks): void {
    $extension = app(PageBuilderExtension::class);
    $virtualExhibitions = app(VirtualExhibitionsPageBuilderExtension::class);

    /*
     * EXHIBITIONS -> PAGE BUILDER INTEGRATION
     * ---------------------------------------
     * The active-plugin hook boundary owns both the Vvveb element and its
     * frontend renderer. Disabling Exhibitions therefore removes the element
     * without placing Exhibitions queries or markup in Page Builder core.
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
    $hooks->addFilter(
        'plugin.page-builder.editor.extensions',
        [$virtualExhibitions, 'editorExtensions'],
        priority: 20,
        acceptedArgs: 2,
    );
    $hooks->addFilter(
        'plugin.page-builder.frontend.html',
        [$virtualExhibitions, 'renderFrontendHtml'],
        priority: 20,
        acceptedArgs: 2,
    );
};
