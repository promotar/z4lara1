<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_students') && ! Schema::hasColumn('lms_students', 'user_id')) {
            Schema::table('lms_students', function (Blueprint $table): void {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                $table->unique('user_id');
            });
        }

        if (! Schema::hasTable('lms_instructors')) {
            Schema::create('lms_instructors', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('status', 32)->default('pending')->index();
                $table->string('avatar_path')->nullable();
                $table->string('avatar_name')->nullable();
                $table->json('avatar_meta')->nullable();
                $table->timestamp('invited_at')->nullable();
                $table->timestamp('last_active_at')->nullable();
                $table->foreignId('created_by')->nullable()->index();
                $table->foreignId('updated_by')->nullable()->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('lms_courses') && ! Schema::hasColumn('lms_courses', 'instructor_id')) {
            Schema::table('lms_courses', function (Blueprint $table): void {
                $table->foreignId('instructor_id')->nullable()->after('instructor_name')->constrained('lms_instructors')->nullOnDelete();
                $table->index(['instructor_id', 'status']);
            });
        }

        if (! Schema::hasTable('lms_invitations')) {
            Schema::create('lms_invitations', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->index();
                $table->string('role', 32)->index();
                $table->foreignId('course_id')->nullable()->constrained('lms_courses')->cascadeOnDelete();
                $table->string('token_hash', 64)->unique();
                $table->string('status', 32)->default('pending')->index();
                $table->json('payload')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('expires_at')->index();
                $table->timestamp('accepted_at')->nullable();
                $table->text('last_error')->nullable();
                $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['email', 'role', 'course_id', 'status'], 'lms_invitation_lookup');
            });
        }

        if (Schema::hasTable('lms_students') && Schema::hasColumn('lms_students', 'user_id')) {
            DB::table('lms_students')
                ->whereNull('user_id')
                ->orderBy('id')
                ->get(['id', 'email'])
                ->each(function (object $student): void {
                    $userId = DB::table('users')->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $student->email)])->value('id');
                    if ($userId && ! DB::table('lms_students')->where('user_id', $userId)->exists()) {
                        DB::table('lms_students')->where('id', $student->id)->update(['user_id' => $userId]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_invitations');

        if (Schema::hasColumn('lms_courses', 'instructor_id')) {
            Schema::table('lms_courses', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('instructor_id');
            });
        }

        Schema::dropIfExists('lms_instructors');

        if (Schema::hasColumn('lms_students', 'user_id')) {
            Schema::table('lms_students', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
