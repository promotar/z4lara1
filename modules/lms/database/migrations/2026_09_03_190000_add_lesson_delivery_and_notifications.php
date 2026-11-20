<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_lessons') && ! Schema::hasColumn('lms_lessons', 'prerequisite_lesson_id')) {
            Schema::table('lms_lessons', function (Blueprint $table): void {
                $table->unsignedBigInteger('prerequisite_lesson_id')->nullable()->after('availability');
            });
        }

        if (
            Schema::hasTable('lms_lessons')
            && Schema::hasColumn('lms_lessons', 'prerequisite_lesson_id')
            && ! Schema::hasIndex('lms_lessons', 'lms_lesson_prerequisite_idx')
        ) {
            Schema::table('lms_lessons', fn (Blueprint $table) => $table->index(
                ['course_id', 'prerequisite_lesson_id'],
                'lms_lesson_prerequisite_idx',
            ));
        }

        if (Schema::hasTable('lms_notifications') && ! Schema::hasColumn('lms_notifications', 'dedup_key')) {
            Schema::table('lms_notifications', function (Blueprint $table): void {
                $table->string('dedup_key', 191)->nullable()->after('type');
            });
        }

        if (
            Schema::hasTable('lms_notifications')
            && Schema::hasColumn('lms_notifications', 'dedup_key')
            && ! Schema::hasIndex('lms_notifications', 'lms_notifications_dedup_uq')
        ) {
            Schema::table('lms_notifications', fn (Blueprint $table) => $table->unique(
                'dedup_key',
                'lms_notifications_dedup_uq',
            ));
        }

        if (! Schema::hasTable('lms_email_notifications')) {
            Schema::create('lms_email_notifications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recipient_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('email');
                $table->string('subject');
                $table->longText('body');
                $table->string('dedup_key', 191)->unique('lms_email_dedup_uq');
                $table->string('status', 24)->default('pending')->index();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('available_at')->nullable()->index();
                $table->timestamp('processing_at')->nullable();
                $table->timestamp('sent_at')->nullable()->index();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['status', 'available_at', 'id'], 'lms_email_dispatch_idx');
            });
        }

        $this->normalizeLessonRules();
        $this->normalizeRequiredFields();
        $this->setDiplomaPrices();
        $this->backfillAccessibleCourses();
    }

    private function normalizeLessonRules(): void
    {
        if (! Schema::hasTable('lms_lessons') || ! Schema::hasColumn('lms_lessons', 'prerequisite_lesson_id')) {
            return;
        }

        DB::table('lms_lessons')
            ->whereNull('deleted_at')
            ->whereNotNull('course_id')
            ->select('course_id')
            ->distinct()
            ->orderBy('course_id')
            ->pluck('course_id')
            ->each(function ($courseId): void {
                $previousLessonId = null;

                DB::table('lms_lessons')
                    ->where('course_id', $courseId)
                    ->whereNull('deleted_at')
                    ->orderBy('module_order')
                    ->orderBy('lesson_order')
                    ->orderBy('id')
                    ->get(['id', 'availability', 'publish_date', 'published_at', 'created_at'])
                    ->each(function (object $lesson) use (&$previousLessonId): void {
                        $availability = in_array($lesson->availability, ['immediate', 'scheduled', 'after_previous'], true)
                            ? $lesson->availability
                            : 'immediate';
                        $values = ['availability' => $availability];

                        if ($availability === 'after_previous') {
                            if ($previousLessonId) {
                                $values['prerequisite_lesson_id'] = $previousLessonId;
                            } else {
                                $values['availability'] = 'immediate';
                                $values['prerequisite_lesson_id'] = null;
                            }
                        } elseif ($availability === 'scheduled' && ! $lesson->publish_date) {
                            // Preserve access to legacy lessons that were marked scheduled without a date.
                            $values['availability'] = 'immediate';
                        } else {
                            $values['prerequisite_lesson_id'] = null;
                        }

                        DB::table('lms_lessons')->where('id', $lesson->id)->update($values);
                        $previousLessonId = (int) $lesson->id;
                    });
            });
    }

    private function normalizeRequiredFields(): void
    {
        if (Schema::hasTable('lms_courses')) {
            foreach (['category' => 'General', 'level' => 'All Levels', 'language' => 'Arabic'] as $column => $default) {
                DB::table('lms_courses')
                    ->where(fn ($query) => $query->whereNull($column)->orWhere($column, ''))
                    ->update([$column => $default]);
            }
        }

        if (Schema::hasTable('lms_lessons')) {
            DB::table('lms_lessons')
                ->where(fn ($query) => $query->whereNull('module_title')->orWhere('module_title', ''))
                ->update(['module_title' => 'Course Lessons']);
            DB::table('lms_lessons')
                ->whereNotIn('access', ['enrolled', 'public', 'instructor'])
                ->orWhereNull('access')
                ->update(['access' => 'enrolled']);
            DB::table('lms_lessons')
                ->whereNotIn('completion_requirement', ['manual', 'video', 'content'])
                ->orWhereNull('completion_requirement')
                ->update(['completion_requirement' => 'manual']);
            DB::table('lms_lessons')
                ->whereNotIn('lesson_type', ['video', 'text', 'pdf'])
                ->orWhereNull('lesson_type')
                ->update(['lesson_type' => 'text']);
        }
    }

    private function setDiplomaPrices(): void
    {
        if (! Schema::hasTable('lms_courses') || ! Schema::hasColumn('lms_courses', 'program_type')) {
            return;
        }

        DB::table('lms_courses')
            ->where('program_type', 'diploma')
            ->update([
                'pricing_type' => 'paid',
                'regular_price' => 1200,
                'sale_price' => 1000,
                'currency' => 'JOD',
            ]);
    }

    private function backfillAccessibleCourses(): void
    {
        if (! Schema::hasTable('lms_course_enrollments') || ! Schema::hasTable('lms_courses')) {
            return;
        }

        $now = now();
        $activeStudentIds = Schema::hasTable('lms_students')
            ? DB::table('lms_students')->where('status', 'active')->whereNull('deleted_at')->whereNotNull('user_id')->pluck('user_id')
            : collect();

        DB::table('lms_courses')
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(50, function ($courses) use ($activeStudentIds, $now): void {
                foreach ($courses as $course) {
                    $rows = [];

                    if ($course->pricing_type === 'free') {
                        foreach ($activeStudentIds as $userId) {
                            $rows[] = [
                                'course_id' => $course->id,
                                'user_id' => $userId,
                                'source' => 'free_course',
                                'enrolled_at' => $now,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    if ($course->instructor_id && Schema::hasTable('lms_instructors')) {
                        $ownerId = DB::table('lms_instructors')->where('id', $course->instructor_id)->value('user_id');
                        if ($ownerId) {
                            $rows[] = [
                                'course_id' => $course->id,
                                'user_id' => $ownerId,
                                'source' => 'instructor_owner',
                                'enrolled_at' => $now,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table('lms_course_enrollments')->insertOrIgnore($chunk);
                    }
                }
            });

        if (Schema::hasTable('lms_students')) {
            DB::table('lms_students')
                ->whereNotNull('user_id')
                ->orderBy('id')
                ->get(['id', 'user_id'])
                ->each(function (object $student): void {
                    $courseIds = DB::table('lms_course_enrollments')
                        ->where('user_id', $student->user_id)
                        ->orderBy('course_id')
                        ->pluck('course_id')
                        ->map(fn ($id): int => (int) $id)
                        ->all();

                    DB::table('lms_students')->where('id', $student->id)->update([
                        'enrolled_course_ids' => json_encode($courseIds, JSON_THROW_ON_ERROR),
                        'enrolled_courses_count' => count($courseIds),
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_email_notifications');

        if (Schema::hasTable('lms_notifications') && Schema::hasColumn('lms_notifications', 'dedup_key')) {
            Schema::table('lms_notifications', function (Blueprint $table): void {
                if (Schema::hasIndex('lms_notifications', 'lms_notifications_dedup_uq')) {
                    $table->dropUnique('lms_notifications_dedup_uq');
                }
                $table->dropColumn('dedup_key');
            });
        }

        if (Schema::hasTable('lms_lessons') && Schema::hasColumn('lms_lessons', 'prerequisite_lesson_id')) {
            Schema::table('lms_lessons', function (Blueprint $table): void {
                if (Schema::hasIndex('lms_lessons', 'lms_lesson_prerequisite_idx')) {
                    $table->dropIndex('lms_lesson_prerequisite_idx');
                }
                $table->dropColumn('prerequisite_lesson_id');
            });
        }
    }
};
