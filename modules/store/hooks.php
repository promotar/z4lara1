<?php

use App\Platform\Core\Hooks\HookManager;
use Modules\Store\Application\PageBuilder\PageBuilderExtension;

return static function (HookManager $hooks): void {
    $extension = app(PageBuilderExtension::class);
    /* Store owns its Page Builder components and public rendering boundary. */
    $hooks->addFilter('plugin.page-builder.editor.extensions', [$extension, 'editorExtensions'], priority: 10, acceptedArgs: 2);
    $hooks->addFilter('plugin.page-builder.frontend.html', [$extension, 'renderFrontendHtml'], priority: 10, acceptedArgs: 2);
};
