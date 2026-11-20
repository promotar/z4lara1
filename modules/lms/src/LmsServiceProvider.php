<?php

namespace Modules\Lms;

use App\Platform\Core\Contracts\UserDeletionParticipant as UserDeletionParticipantContract;
use App\Platform\Core\Hooks\HookManager;
use App\Platform\Core\Seo\SeoRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Lms\Console\DispatchLmsEmailNotifications;
use Modules\Lms\Models\Course;
use Modules\Lms\Models\Lesson;
use Modules\Lms\Models\Student;
use Modules\Lms\Observers\CourseObserver;
use Modules\Lms\Observers\LessonObserver;
use Modules\Lms\Observers\StudentObserver;
use Modules\Lms\Services\LmsUserDeletionParticipant;
use Modules\Lms\Support\PlatformBrand;

class LmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        require_once __DIR__.'/Services/AccountSectionProvider.php';
        require_once __DIR__.'/Services/LmsNotificationService.php';
        require_once __DIR__.'/Services/LessonAvailabilityService.php';
        require_once __DIR__.'/Services/LmsPublicationService.php';
        require_once __DIR__.'/Services/LmsUserDeletionParticipant.php';

        $this->app->singleton(Services\AccountSectionProvider::class);
        $this->app->singleton(Services\LmsNotificationService::class);
        $this->app->singleton(Services\LessonAvailabilityService::class);
        $this->app->singleton(Services\LmsPublicationService::class);
        $this->app->singleton(LmsUserDeletionParticipant::class);
        $this->app->tag(LmsUserDeletionParticipant::class, UserDeletionParticipantContract::class);
    }

    public function boot(): void
    {
        require_once __DIR__.'/Support/ResolvesPlatformMedia.php';
        require_once __DIR__.'/Support/VideoEmbedResolver.php';
        require_once __DIR__.'/Models/Course.php';
        require_once __DIR__.'/Models/Lesson.php';
        require_once __DIR__.'/Models/Student.php';
        require_once __DIR__.'/Models/Instructor.php';
        require_once __DIR__.'/Models/InstructorRequest.php';
        require_once __DIR__.'/Models/LmsNotification.php';
        require_once __DIR__.'/Models/LmsEmailNotification.php';
        require_once __DIR__.'/Models/Invitation.php';
        require_once __DIR__.'/Models/CourseCode.php';
        require_once __DIR__.'/Models/CourseEnrollment.php';
        require_once __DIR__.'/Models/LessonProgress.php';
        require_once __DIR__.'/Models/Template.php';
        require_once __DIR__.'/Services/TemplateSettings.php';
        require_once __DIR__.'/Services/CourseTemplateRenderer.php';
        require_once __DIR__.'/Services/PageBuilderExtension.php';
        require_once __DIR__.'/Seo/LmsSeoContributor.php';
        require_once __DIR__.'/Seo/LmsSitemapContributor.php';
        require_once __DIR__.'/Observers/CourseObserver.php';
        require_once __DIR__.'/Observers/LessonObserver.php';
        require_once __DIR__.'/Observers/StudentObserver.php';
        require_once __DIR__.'/Console/DispatchLmsEmailNotifications.php';
        require_once __DIR__.'/Http/Controllers/Admin/LmsAdminController.php';
        require_once __DIR__.'/Http/Controllers/Admin/LmsPeopleController.php';
        require_once __DIR__.'/Http/Controllers/Admin/TemplateController.php';
        require_once __DIR__.'/Http/Controllers/Frontend/LmsFrontendController.php';
        require_once __DIR__.'/Http/Controllers/Frontend/LmsPortalController.php';

        View::addNamespace('lms', dirname(__DIR__).'/resources/views');

        View::composer('lms::*', function ($view): void {
            $view->with('lmsBrand', PlatformBrand::name());
            $view->with('lmsBrandInitial', PlatformBrand::initial());
        });
        Course::observe($this->app->make(CourseObserver::class));
        Lesson::observe($this->app->make(LessonObserver::class));
        Student::observe($this->app->make(StudentObserver::class));

        $seoRegistry = $this->app->make(SeoRegistry::class);
        $seoRegistry->add($this->app->make(Seo\LmsSeoContributor::class), priority: 30);
        $seoRegistry->addSitemap($this->app->make(Seo\LmsSitemapContributor::class), priority: 30);

        if ($this->app->runningInConsole()) {
            $this->commands([DispatchLmsEmailNotifications::class]);
            $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('lms:dispatch-email-notifications')
                    ->everyMinute()
                    ->withoutOverlapping(15);
            });
        }

        $this->app->make(HookManager::class)->addFilter(
            'frontend.account.sections',
            [$this->app->make(Services\AccountSectionProvider::class), 'append'],
            priority: 20,
            acceptedArgs: 2,
        );
    }
}
