<?php

use App\Platform\Core\Hooks\HookManager;
use Modules\Lms\Services\LmsCourseCardsPageBuilderExtension;
use Modules\Lms\Services\LmsInstructorsPageBuilderExtension;

return static function (HookManager $hooks): void {
    $extension = app(LmsCourseCardsPageBuilderExtension::class);
    $instructors = app(LmsInstructorsPageBuilderExtension::class);

    /*
     * LMS -> PAGE BUILDER INTEGRATION
     * --------------------------------
     * This is the only integration boundary. The platform loads plugin hook files
     * only while their plugin is active, so the Course Cards element and its renderer
     * automatically disappear when LMS is disabled or uninstalled. Do not move
     * LMS models, templates, or rendering logic into the Page Builder module.
     */
    $hooks->addFilter('plugin.page-builder.editor.extensions', [$extension, 'editorExtensions'], priority: 10, acceptedArgs: 2);
    $hooks->addFilter('plugin.page-builder.frontend.html', [$extension, 'renderFrontendHtml'], priority: 10, acceptedArgs: 2);
    $hooks->addFilter('plugin.page-builder.editor.extensions', [$instructors, 'editorExtensions'], priority: 20, acceptedArgs: 2);
    $hooks->addFilter('plugin.page-builder.frontend.html', [$instructors, 'renderFrontendHtml'], priority: 20, acceptedArgs: 2);
};
