<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lms_courses')) {
            $needsProgramType = ! Schema::hasColumn('lms_courses', 'program_type');
            $needsReviewStatus = ! Schema::hasColumn('lms_courses', 'review_status');
            Schema::table('lms_courses', function (Blueprint $table) use ($needsProgramType, $needsReviewStatus): void {
                if ($needsProgramType) {
                    $table->string('program_type', 24)->default('course')->after('title')->index();
                }
                if ($needsReviewStatus) {
                    $table->string('review_status', 24)->default('none')->after('status')->index();
                }
            });
        }

        if (Schema::hasTable('lms_lessons') && ! Schema::hasColumn('lms_lessons', 'review_status')) {
            Schema::table('lms_lessons', function (Blueprint $table): void {
                $table->string('review_status', 24)->default('none')->after('status')->index();
            });
        }

        if (! Schema::hasTable('lms_instructor_requests')) {
            Schema::create('lms_instructor_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('status', 24)->default('pending')->index();
                $table->string('expertise', 255)->nullable();
                $table->text('message')->nullable();
                $table->timestamp('requested_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('decision_note')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('lms_notifications')) {
            Schema::create('lms_notifications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 80)->index();
                $table->string('title', 255);
                $table->text('body')->nullable();
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['recipient_id', 'read_at', 'created_at'], 'lms_notify_recipient_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_notifications');
        Schema::dropIfExists('lms_instructor_requests');

        if (Schema::hasTable('lms_lessons') && Schema::hasColumn('lms_lessons', 'review_status')) {
            Schema::table('lms_lessons', fn (Blueprint $table) => $table->dropColumn('review_status'));
        }

        if (Schema::hasTable('lms_courses')) {
            $hasReviewStatus = Schema::hasColumn('lms_courses', 'review_status');
            $hasProgramType = Schema::hasColumn('lms_courses', 'program_type');
            Schema::table('lms_courses', function (Blueprint $table) use ($hasReviewStatus, $hasProgramType): void {
                if ($hasReviewStatus) {
                    $table->dropColumn('review_status');
                }
                if ($hasProgramType) {
                    $table->dropColumn('program_type');
                }
            });
        }
    }
};
