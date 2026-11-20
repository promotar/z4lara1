<?php

namespace Modules\Blog;

use App\Platform\Core\Contracts\UserDeletionParticipant as UserDeletionParticipantContract;
use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Seo\SeoRegistry;
use App\Platform\Core\Services\PluginRuntimeGate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Blog\Services\BlogUserDeletionParticipant;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/Services/BlogUserDeletionParticipant.php';
        require_once __DIR__.'/Services/AuthorAccessService.php';
        require_once __DIR__.'/Services/AccountSectionProvider.php';

        $this->app->singleton(BlogUserDeletionParticipant::class);
        $this->app->singleton(Services\AuthorAccessService::class);
        $this->app->singleton(Services\AccountSectionProvider::class);
        $this->app->tag(BlogUserDeletionParticipant::class, UserDeletionParticipantContract::class);
    }

    public function boot(): void
    {
        if (! $this->pluginEnabled()) {
            return;
        }

        require_once __DIR__.'/Http/Controllers/BlogController.php';
        require_once __DIR__.'/Http/Controllers/Admin/PostController.php';
        require_once __DIR__.'/Http/Controllers/Admin/CategoryController.php';
        require_once __DIR__.'/Http/Controllers/Admin/TagController.php';
        require_once __DIR__.'/Http/Controllers/Admin/TemplateController.php';
        require_once __DIR__.'/Http/Controllers/Admin/SettingsController.php';
        require_once __DIR__.'/Http/Controllers/Admin/AuthorController.php';
        require_once __DIR__.'/Http/Controllers/Frontend/WriterController.php';
        require_once __DIR__.'/Http/Middleware/MoveBlogSeoToHeadStart.php';
        require_once __DIR__.'/Models/Post.php';
        require_once __DIR__.'/Models/Category.php';
        require_once __DIR__.'/Models/Tag.php';
        require_once __DIR__.'/Models/Media.php';
        require_once __DIR__.'/Models/Revision.php';
        require_once __DIR__.'/Models/PostMeta.php';
        require_once __DIR__.'/Models/Template.php';
        require_once __DIR__.'/Models/Author.php';
        require_once __DIR__.'/Seo/BlogSeoContributor.php';
        require_once __DIR__.'/Seo/BlogSitemapContributor.php';
        require_once __DIR__.'/Services/SeoScoreCalculator.php';
        require_once __DIR__.'/Services/TemplateRenderer.php';
        require_once __DIR__.'/Services/PageBuilderExtension.php';
        require_once __DIR__.'/Services/TemplateSettings.php';

        View::addNamespace('blog', dirname(__DIR__).'/resources/views');
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views', 'blog');
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');

        $seoRegistry = $this->app->make(SeoRegistry::class);
        $seoRegistry->add($this->app->make(Seo\BlogSeoContributor::class), priority: 20);
        $seoRegistry->addSitemap($this->app->make(Seo\BlogSitemapContributor::class), priority: 20);

        $this->app->make(HookManager::class)->addFilter(
            'frontend.account.sections',
            [$this->app->make(Services\AccountSectionProvider::class), 'append'],
            priority: 35,
            acceptedArgs: 2,
        );
    }

    private function pluginEnabled(): bool
    {
        return $this->app->make(PluginRuntimeGate::class)->allows('blog');
    }
}
