<?php

namespace Modules\Lms;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class LmsLifecycle
{
    public function activate(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
            return;
        }

        $role = Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        $basePermissions = [
            'lms.instructor.portal',
            'lms.instructor.courses.manage',
            'lms.instructor.lessons.manage',
        ];
        $permissions = collect($basePermissions)->map(
            fn (string $name): Permission => Permission::firstOrCreate([
                'name' => $name,
                'guard_name' => 'web',
            ]),
        );

        if ($permissions->isNotEmpty()) {
            $role->givePermissionTo($permissions);
        }

        if (Schema::hasTable('lms_instructors')) {
            DB::table('lms_instructors')
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->whereNotNull('user_id')
                ->orderBy('id')
                ->pluck('user_id')
                ->unique()
                ->chunk(100)
                ->each(function ($ids) use ($role): void {
                    User::query()->whereIn('id', $ids)->get()->each(function (User $user) use ($role): void {
                        if (! $user->hasRole($role)) {
                            $user->assignRole($role);
                        }
                    });
                });
        }

        $this->reconcileInstructorOwnership();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Keep plugin upgrades safe when the current PHP request still has service
     * classes from the previously installed release loaded in memory.
     */
    private function reconcileInstructorOwnership(): void
    {
        if (! Schema::hasTable('lms_courses')
            || ! Schema::hasTable('lms_instructors')
            || ! Schema::hasTable('lms_course_enrollments')) {
            return;
        }

        $activeInstructorUsers = DB::table('lms_instructors')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('user_id')
            ->pluck('user_id', 'id')
            ->map(fn ($userId): int => (int) $userId);
        $studentTableAvailable = Schema::hasTable('lms_students');
        $affectedUserIds = collect();

        DB::table('lms_courses')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($courses) use ($activeInstructorUsers, $studentTableAvailable, $affectedUserIds): void {
                foreach ($courses as $course) {
                    $currentUserId = $course->instructor_id
                        ? $activeInstructorUsers->get((int) $course->instructor_id)
                        : null;
                    $expectedOwnerId = $course->status === 'published' && $currentUserId
                        ? (int) $currentUserId
                        : null;

                    $ownerUserIds = DB::table('lms_course_enrollments')
                        ->where('course_id', $course->id)
                        ->where('source', 'instructor_owner')
                        ->pluck('user_id')
                        ->map(fn ($userId): int => (int) $userId);

                    foreach ($ownerUserIds as $ownerUserId) {
                        if ($expectedOwnerId === $ownerUserId) {
                            continue;
                        }

                        $enrollment = DB::table('lms_course_enrollments')
                            ->where('course_id', $course->id)
                            ->where('user_id', $ownerUserId)
                            ->where('source', 'instructor_owner');
                        $retainFreeAccess = $studentTableAvailable
                            && $course->status === 'published'
                            && $course->pricing_type === 'free'
                            && DB::table('lms_students')
                                ->where('user_id', $ownerUserId)
                                ->where('status', 'active')
                                ->whereNull('deleted_at')
                                ->exists();

                        if ($retainFreeAccess) {
                            $enrollment->update(['source' => 'free_course', 'updated_at' => now()]);
                        } else {
                            $enrollment->delete();
                        }

                        $affectedUserIds->push($ownerUserId);
                    }

                    if ($expectedOwnerId) {
                        $now = now();
                        DB::table('lms_course_enrollments')->insertOrIgnore([[
                            'course_id' => (int) $course->id,
                            'user_id' => $expectedOwnerId,
                            'source' => 'instructor_owner',
                            'enrolled_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]]);
                        $affectedUserIds->push($expectedOwnerId);
                    }
                }
            }, 'id');

        if (! $studentTableAvailable
            || ! Schema::hasColumn('lms_students', 'enrolled_course_ids')
            || ! Schema::hasColumn('lms_students', 'enrolled_courses_count')) {
            return;
        }

        $affectedUserIds->unique()->each(function (int $userId): void {
            $courseIds = DB::table('lms_course_enrollments')
                ->where('user_id', $userId)
                ->orderBy('course_id')
                ->pluck('course_id')
                ->map(fn ($courseId): int => (int) $courseId)
                ->all();

            DB::table('lms_students')->where('user_id', $userId)->update([
                'enrolled_course_ids' => json_encode($courseIds, JSON_THROW_ON_ERROR),
                'enrolled_courses_count' => count($courseIds),
                'updated_at' => now(),
            ]);
        });
    }
}
