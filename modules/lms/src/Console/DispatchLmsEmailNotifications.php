<?php

namespace Modules\Lms\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Lms\Models\LmsEmailNotification;
use Throwable;

final class DispatchLmsEmailNotifications extends Command
{
    protected $signature = 'lms:dispatch-email-notifications';

    protected $description = 'Send queued LMS notification emails within the provider-safe rate limit.';

    public function handle(): int
    {
        $ids = DB::transaction(function (): array {
            // The oldest row is the database mutex for this outbox. Counting both
            // sent and in-flight reservations keeps the rate limit safe even when
            // two workers or a manual command start at the same moment.
            LmsEmailNotification::query()->orderBy('id')->lockForUpdate()->first();

            LmsEmailNotification::query()
                ->where('status', 'sending')
                ->where('processing_at', '<=', now()->subMinutes(30))
                ->where('attempts', '<', 5)
                ->update(['status' => 'pending', 'processing_at' => null, 'available_at' => now()]);

            $reservedInWindow = LmsEmailNotification::query()
                ->where(function ($query): void {
                    $query
                        ->where(fn ($sent) => $sent->where('status', 'sent')->where('sent_at', '>=', now()->subMinutes(10)))
                        ->orWhere(fn ($sending) => $sending->where('status', 'sending')->where('processing_at', '>=', now()->subMinutes(10)));
                })
                ->count();
            $slots = max(0, 5 - $reservedInWindow);
            if ($slots === 0) {
                return [];
            }

            $messages = LmsEmailNotification::query()
                ->where('status', 'pending')
                ->where('attempts', '<', 5)
                ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($slots)
                ->get();

            foreach ($messages as $message) {
                $message->forceFill([
                    'status' => 'sending',
                    'processing_at' => now(),
                    'attempts' => $message->attempts + 1,
                ])->save();
            }

            return $messages->pluck('id')->map(fn ($id): int => (int) $id)->all();
        });

        if ($ids === []) {
            $this->line('No LMS email slots are available in the current 10-minute window.');

            return self::SUCCESS;
        }

        foreach ($ids as $id) {
            $message = LmsEmailNotification::query()->find($id);
            if (! $message || $message->status !== 'sending') {
                continue;
            }

            try {
                Mail::raw($message->body, function ($mail) use ($message): void {
                    $mail->to($message->email)->subject($message->subject);
                });

                $message->forceFill([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'processing_at' => null,
                    'last_error' => null,
                ])->save();
            } catch (Throwable $exception) {
                $failed = $message->attempts >= 5;
                $message->forceFill([
                    'status' => $failed ? 'failed' : 'pending',
                    'available_at' => $failed ? null : now()->addMinutes(10),
                    'processing_at' => null,
                    'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                ])->save();
            }
        }

        $this->info(count($ids).' LMS email notification(s) processed.');

        return self::SUCCESS;
    }
}
