<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lms_lessons')) {
            return;
        }

        DB::table('lms_lessons')
            ->select(['id', 'video_path'])
            ->whereNotNull('video_path')
            ->where('video_path', '!=', '')
            ->where('video_path', 'not like', 'private:%')
            ->orderBy('id')
            ->chunkById(25, function ($lessons): void {
                foreach ($lessons as $lesson) {
                    $this->moveLessonVideo((int) $lesson->id, (string) $lesson->video_path);
                }
            });
    }

    public function down(): void
    {
        // Intentionally keep protected lesson media private on rollback.
    }

    private function moveLessonVideo(int $lessonId, string $storedPath): void
    {
        $source = ltrim(trim($storedPath), '/');
        if ($source === '' || str_contains($source, '..') || ! Storage::disk('public')->exists($source)) {
            return;
        }

        $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
        if (! in_array($extension, ['mp4', 'webm', 'mov', 'm4v', 'ogg'], true)) {
            return;
        }

        $sourceSize = (int) Storage::disk('public')->size($source);
        $target = 'lms/protected-videos/'.hash('sha256', $lessonId.'|'.$source.'|'.$sourceSize).'.'.$extension;

        if (! Storage::disk('local')->exists($target)) {
            $stream = Storage::disk('public')->readStream($source);
            if (! is_resource($stream)) {
                throw new RuntimeException('Unable to read an existing LMS lesson video during private-storage migration.');
            }

            try {
                if (! Storage::disk('local')->writeStream($target, $stream)) {
                    throw new RuntimeException('Unable to protect an existing LMS lesson video during migration.');
                }
            } finally {
                fclose($stream);
            }
        }

        if ((int) Storage::disk('local')->size($target) !== $sourceSize) {
            Storage::disk('local')->delete($target);
            throw new RuntimeException('Protected LMS lesson video failed its size verification.');
        }

        DB::table('lms_lessons')->where('id', $lessonId)->update([
            'video_path' => 'private:'.$target,
            'updated_at' => now(),
        ]);

        Storage::disk('public')->delete($source);
    }
};
