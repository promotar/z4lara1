<?php

use Illuminate\Support\Facades\Route;
use Modules\Lms\Http\Controllers\Frontend\LmsFrontendController;
use Modules\Lms\Http\Controllers\Frontend\LmsPortalController;

Route::get('/lms/invitations/{token}', [LmsPortalController::class, 'invitation'])->name('invitations.show');
Route::post('/lms/invitations/{token}', [LmsPortalController::class, 'acceptInvitation'])->name('invitations.accept');

Route::get('/lms/media-file/{path}', [LmsPortalController::class, 'mediaFile'])->where('path', '.*')->name('media.file');

Route::middleware('auth')->group(function (): void {
    Route::get('/lms/media-studio', [LmsPortalController::class, 'mediaIndex'])->name('media.index');
    Route::post('/lms/media-studio', [LmsPortalController::class, 'mediaStore'])->name('media.store');
    Route::delete('/lms/media-studio', [LmsPortalController::class, 'mediaDelete'])->name('media.destroy');
    Route::post('/lms/course-image', [LmsPortalController::class, 'uploadCourseImage'])->name('course-image.upload');
    Route::get('/lms/notifications', [LmsPortalController::class, 'notifications'])->name('notifications.index');
    Route::patch('/lms/notifications/{notification}/read', [LmsPortalController::class, 'readNotification'])->name('notifications.read');
    Route::post('/lms/notifications/read-all', [LmsPortalController::class, 'readAllNotifications'])->name('notifications.read-all');
    Route::post('/account/course-codes', [LmsPortalController::class, 'generateCourseCodes'])->name('codes.generate');
    Route::get('/account/course-codes/export', [LmsPortalController::class, 'exportCourseCodes'])->name('codes.export');

    Route::get('/teach/apply', [LmsPortalController::class, 'instructorRequest'])->name('instructor-request.create');
    Route::post('/teach/apply', [LmsPortalController::class, 'storeInstructorRequest'])->name('instructor-request.store');

    Route::prefix('my-learning')->name('student.')->group(function (): void {
        Route::get('/', [LmsPortalController::class, 'studentOverview'])->name('overview');
        Route::get('/courses', [LmsPortalController::class, 'studentCourses'])->name('courses');
        Route::get('/settings', [LmsPortalController::class, 'studentSettings'])->name('settings');
        Route::put('/settings', [LmsPortalController::class, 'updateStudentSettings'])->name('settings.update');
    });

    Route::prefix('instructor-dashboard')->name('instructor.')->group(function (): void {
        Route::get('/', [LmsPortalController::class, 'instructorCourses'])->name('courses');
        Route::get('/courses/create', [LmsPortalController::class, 'createInstructorCourse'])->name('courses.create');
        Route::post('/courses', [LmsPortalController::class, 'storeInstructorCourse'])->name('courses.store');
        Route::get('/courses/{course}/edit', [LmsPortalController::class, 'editInstructorCourse'])->name('courses.edit');
        Route::put('/courses/{course}', [LmsPortalController::class, 'updateInstructorCourse'])->name('courses.update');
        Route::put('/courses/{course}/curriculum', [LmsPortalController::class, 'updateInstructorCourseCurriculum'])->name('courses.curriculum');
        Route::get('/lessons', [LmsPortalController::class, 'instructorLessons'])->name('lessons');
        Route::get('/lessons/create', [LmsPortalController::class, 'createInstructorLesson'])->name('lessons.create');
        Route::post('/lessons', [LmsPortalController::class, 'storeInstructorLesson'])->name('lessons.store');
        Route::get('/lessons/{lesson}/edit', [LmsPortalController::class, 'editInstructorLesson'])->name('lessons.edit');
        Route::put('/lessons/{lesson}', [LmsPortalController::class, 'updateInstructorLesson'])->name('lessons.update');
        Route::delete('/lessons/{lesson}', [LmsPortalController::class, 'deleteInstructorLesson'])->name('lessons.destroy');
        Route::get('/courses/trash', [LmsPortalController::class, 'instructorCourseTrash'])->name('courses.trash');
        Route::delete('/courses/{course}', [LmsPortalController::class, 'deleteInstructorCourse'])->name('courses.destroy');
        Route::post('/courses/{course}/restore', [LmsPortalController::class, 'restoreInstructorCourse'])->name('courses.restore');
        Route::delete('/courses/{course}/force', [LmsPortalController::class, 'forceInstructorCourse'])->name('courses.force-destroy');
        Route::get('/lessons/trash', [LmsPortalController::class, 'instructorLessonTrash'])->name('lessons.trash');
        Route::post('/lessons/{lesson}/restore', [LmsPortalController::class, 'restoreInstructorLesson'])->name('lessons.restore');
        Route::delete('/lessons/{lesson}/force', [LmsPortalController::class, 'forceInstructorLesson'])->name('lessons.force-destroy');
        Route::get('/students', [LmsPortalController::class, 'instructorStudents'])->name('students');
        Route::get('/settings', [LmsPortalController::class, 'instructorSettings'])->name('settings');
        Route::put('/settings', [LmsPortalController::class, 'updateInstructorSettings'])->name('settings.update');
    });
});

Route::get('/courses', [LmsFrontendController::class, 'coursesPage'])->name('courses');
Route::get('/lms/assets/templates/{slug}.js', [LmsFrontendController::class, 'templateScript'])->name('template-script');
Route::post('/courses/{course:slug}/player/{lesson:slug}/progress', [LmsFrontendController::class, 'updateLessonProgress'])->name('courses.player.progress');
Route::get('/courses/{course:slug}/player/{lesson:slug}/video', [LmsFrontendController::class, 'lessonVideo'])
    ->middleware('signed:relative')
    ->name('courses.player.video');
Route::get('/courses/{course:slug}/preview/{lesson:slug}', [LmsFrontendController::class, 'coursePreview'])->name('courses.preview');
Route::get('/courses/{course:slug}/player/{lesson:slug?}', [LmsFrontendController::class, 'coursePlayer'])->name('courses.player');
Route::get('/courses/{course:slug}', [LmsFrontendController::class, 'courseDetails'])->name('courses.show');
Route::get('/instructors/{instructor}', [LmsFrontendController::class, 'instructorProfile'])->name('instructors.show');
Route::get('/lms/courses/catalog', [LmsFrontendController::class, 'catalog'])->name('courses.catalog');
Route::post('/lms/courses/{course:slug}/coupon', [LmsFrontendController::class, 'applyCoupon'])->name('courses.coupon');
Route::post('/lms/courses/{course:slug}/training-mode', [LmsFrontendController::class, 'updateTrainingMode'])->name('courses.training-mode');
