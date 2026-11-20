<?php

namespace Modules\Exhibition\Application\Notifications;

use Modules\Exhibition\Contracts\Identity\CoreUserGateway;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Exhibition\Mail\ExhibitionDeliveryMail;
use Modules\Exhibition\Application\Settings\ExhibitionSettingsService;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

final class ExhibitionNotificationService
{
    private const EVENT_SETTING = [
        'artwork_sold' => 'exhibitions.notifications.artwork_sold.artist',
        'payout_sent' => 'exhibitions.notifications.payout_paid.artist',
        'payout_completed' => 'exhibitions.notifications.payout_paid.artist',
        'payout_failed' => 'exhibitions.notifications.payout_paid.artist',
    ];

    public function __construct(
        private readonly ExhibitionSettingsService $settings,
        private readonly CoreUserGateway $users,
    ) {}

    public const EVENTS = [
        'artist_application_received' => 'artist',
        'additional_documents_requested' => 'artist',
        'artist_accepted' => 'artist',
        'artist_rejected' => 'artist',
        'artist_account_activated' => 'artist',
        'artwork_submitted' => 'artist_and_reviewers',
        'artwork_data_changes_requested' => 'artist',
        'image_changes_requested' => 'artist',
        'authenticity_documents_requested' => 'artist',
        'pricing_review_requested' => 'artist_and_reviewers',
        'artwork_approved' => 'artist',
        'artwork_published' => 'artist',
        'artwork_held_reserved' => 'artist',
        'artwork_sold' => 'artist',
        'artist_preparation_requested' => 'artist',
        'pickup_appointment' => 'artist',
        'shipment_update' => 'artist',
        'delivery_completed' => 'artist',
        'return_requested' => 'artist_and_operations',
        'settlement_eligibility' => 'artist_and_finance',
        'payout_requested' => 'artist_and_finance',
        'payout_request_declined' => 'artist',
        'payout_sent' => 'artist',
        'payout_completed' => 'artist',
        'payout_failed' => 'artist_and_finance',
        'dispute_opened_or_changed' => 'case_parties_and_support',
        'virtual_placement_submitted' => 'reviewers',
        'virtual_placement_changes_requested' => 'artist',
        'virtual_placement_approved' => 'artist',
        'virtual_placement_rejected' => 'artist',
        'virtual_placement_published' => 'artist',
        'virtual_placement_removed' => 'artist',
    ];

    public function catalog(): array
    {
        $catalog = [];

        foreach (self::EVENTS as $key => $recipient) {
            $catalog[$key] = [
                'recipient_rule' => $recipient,
                'channels' => ['database', 'email'],
                'subject' => ucwords(str_replace('_', ' ', $key)),
                'body' => '{{message}}',
            ];
        }

        return $catalog;
    }

    public function dispatchCommitted(
        string $event,
        array $recipients,
        array $payload,
        string $idempotencyBase,
        ?string $locale = null,
    ): void {
        if (! Schema::hasTable('exhibition_notification_deliveries')) {
            return;
        }

        if (! isset(self::EVENTS[$event])) {
            throw new DomainException('Unknown Exhibitions notification event.');
        }

        $settingKey = self::EVENT_SETTING[$event] ?? null;
        if ($settingKey !== null && ! (bool) $this->settings->value($settingKey, true)) {
            return;
        }

        $locale = trim((string) ($locale ?: app()->getLocale()));
        $safe = $this->safePayload($payload);
        $work = function () use ($event, $recipients, $safe, $idempotencyBase, $locale): void {
            foreach (array_values(array_unique(array_map('intval', $recipients))) as $userId) {
                foreach (['database', 'email'] as $channel) {
                    $this->createDelivery(
                        $event,
                        $channel,
                        $userId,
                        $locale,
                        $safe,
                        $idempotencyBase.':'.$userId.':'.$channel,
                    );
                }
            }
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($work);
        } else {
            $work();
        }
    }

    /**
     * Attempt one delivery. The atomic processing claim prevents two workers
     * from sending the same delivery concurrently.
     */
    public function attempt(string $publicId, ?callable $sender = null): object
    {
        $row = $this->find($publicId);

        if (in_array($row->state, ['delivered', 'failed'], true)) {
            return $row;
        }

        $processingTimeout = max(1, (int) config('exhibitions.notifications.processing_timeout_minutes', 15));
        $staleBefore = now()->subMinutes($processingTimeout);
        $claimed = DB::table('exhibition_notification_deliveries')
            ->where('id', $row->id)
            ->where('attempt_count', $row->attempt_count)
            ->where(function ($query) use ($staleBefore): void {
                $query->whereIn('state', ['pending', 'retry_scheduled'])
                    ->orWhere(function ($stale) use ($staleBefore): void {
                        $stale->where('state', 'processing')->where('updated_at', '<=', $staleBefore);
                    });
            })
            ->update(['state' => 'processing', 'updated_at' => now()]);

        if ($claimed !== 1) {
            return $this->find($publicId);
        }

        $attempt = (int) $row->attempt_count + 1;

        try {
            if ($sender !== null) {
                $sender($row);
            } else {
                $this->sendEmail($row);
            }

            DB::transaction(function () use ($row, $attempt): void {
                $this->attemptRow($row->id, $attempt, 'delivered');
                DB::table('exhibition_notification_deliveries')->where('id', $row->id)->update([
                    'state' => 'delivered',
                    'attempt_count' => $attempt,
                    'delivered_at' => now(),
                    'next_attempt_at' => null,
                    'failure_class' => null,
                    'failure_message' => null,
                    'updated_at' => now(),
                ]);
            });
        } catch (Throwable $exception) {
            $transient = $this->isTransient($exception);
            $final = ! $transient || $attempt >= (int) $row->max_attempts;
            $state = $final ? 'failed' : 'retry_scheduled';

            DB::transaction(function () use ($row, $attempt, $state, $exception, $final): void {
                $message = $this->redact($exception->getMessage());
                $this->attemptRow($row->id, $attempt, $state, $exception::class, $message);
                DB::table('exhibition_notification_deliveries')->where('id', $row->id)->update([
                    'state' => $state,
                    'attempt_count' => $attempt,
                    'next_attempt_at' => $final ? null : now()->addMinutes($this->retryMinutes($attempt)),
                    'failure_class' => $exception::class,
                    'failure_message' => $message,
                    'updated_at' => now(),
                ]);
            });
        }

        return $this->find($publicId);
    }

    /**
     * Process a deterministic, bounded batch of pending or due retry emails.
     *
     * @return array{processed:int,delivered:int,retry_scheduled:int,failed:int}
     */
    public function processDue(int $limit = 100): array
    {
        if ($limit < 1 || $limit > 1000) {
            throw new DomainException('Notification batch limit must be between 1 and 1,000.');
        }

        $ids = DB::table('exhibition_notification_deliveries')
            ->where('channel', 'email')
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('state', 'pending')
                        ->where(function ($due): void {
                            $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
                        });
                })->orWhere(function ($retry): void {
                    $retry->where('state', 'retry_scheduled')->where('next_attempt_at', '<=', now());
                })->orWhere(function ($stale): void {
                    $stale->where('state', 'processing')->where(
                        'updated_at',
                        '<=',
                        now()->subMinutes(max(1, (int) config('exhibitions.notifications.processing_timeout_minutes', 15))),
                    );
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('public_id');

        $result = ['processed' => 0, 'delivered' => 0, 'retry_scheduled' => 0, 'failed' => 0];

        foreach ($ids as $id) {
            $delivery = $this->attempt((string) $id);
            $result['processed']++;

            if (isset($result[$delivery->state])) {
                $result[$delivery->state]++;
            }
        }

        return $result;
    }

    public function updateTemplate(
        int $actor,
        string $event,
        string $channel,
        string $locale,
        string $subject,
        string $body,
        string $reason,
    ): object {
        if (
            ! isset(self::EVENTS[$event])
            || ! in_array($channel, ['database', 'email'], true)
            || ! preg_match('/^[a-z]{2}(?:[-_][A-Z]{2})?$/', $locale)
        ) {
            throw new DomainException('Invalid notification template scope.');
        }

        if (trim($subject) === '' || trim($body) === '' || mb_strlen(trim($reason)) < 8) {
            throw new DomainException('Template subject, body, and traceable reason are required.');
        }

        return DB::transaction(function () use ($actor, $event, $channel, $locale, $subject, $body, $reason): object {
            $row = DB::table('exhibition_notification_templates')
                ->where('event_key', $event)
                ->where('channel', $channel)
                ->where('locale', $locale)
                ->first();
            $version = (int) ($row?->version ?? 0) + 1;

            DB::table('exhibition_notification_templates')->updateOrInsert(
                ['event_key' => $event, 'channel' => $channel, 'locale' => $locale],
                [
                    'subject' => trim($subject),
                    'body' => trim($body),
                    'version' => $version,
                    'active' => true,
                    'updated_by' => $actor,
                    'created_at' => $row?->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );
            DB::table('exhibition_notification_template_history')->insert([
                'event_key' => $event,
                'channel' => $channel,
                'locale' => $locale,
                'version' => $version,
                'subject' => trim($subject),
                'body' => trim($body),
                'actor_user_id' => $actor,
                'reason' => trim($reason),
                'created_at' => now(),
            ]);

            return DB::table('exhibition_notification_templates')
                ->where('event_key', $event)
                ->where('channel', $channel)
                ->where('locale', $locale)
                ->first();
        });
    }

    public function markRead(int $userId, string $publicId): void
    {
        DB::table('exhibition_notification_deliveries')
            ->where('public_id', $publicId)
            ->where('recipient_user_id', $userId)
            ->where('channel', 'database')
            ->update(['read_at' => now(), 'updated_at' => now()]);
    }

    private function sendEmail(object $delivery): void
    {
        if ($delivery->channel !== 'email') {
            throw new DomainException('Only email deliveries can be sent by the mail transport.');
        }

        $recipient = $this->users->find((int) $delivery->recipient_user_id);

        if ($recipient === null) {
            throw new DomainException('The notification recipient no longer exists.');
        }

        $email = $recipient->email;

        if (! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('The notification recipient has no valid email address.');
        }

        $subject = $this->safeSubject((string) $delivery->subject);
        $body = $this->redact((string) $delivery->body);
        $link = $this->safeLink($delivery->link === null ? null : (string) $delivery->link);

        if ($link !== null) {
            $body .= "\n\n".rtrim((string) config('app.url'), '/').$link;
        }

        Mail::send(new ExhibitionDeliveryMail($email, $subject, $body));
    }

    private function createDelivery(
        string $event,
        string $channel,
        int $userId,
        string $locale,
        array $payload,
        string $key,
    ): void {
        $template = DB::table('exhibition_notification_templates')
            ->where('event_key', $event)
            ->where('channel', $channel)
            ->where('active', true)
            ->orderByRaw(
                'CASE WHEN locale = ? THEN 0 WHEN locale = ? THEN 1 ELSE 2 END',
                [$locale, (string) config('app.fallback_locale', 'en')],
            )
            ->first();
        $default = $this->catalog()[$event];
        $link = $this->safeLink(isset($payload['link']) ? (string) $payload['link'] : null);

        DB::table('exhibition_notification_deliveries')->insertOrIgnore([
            'public_id' => (string) Str::uuid(),
            'event_key' => $event,
            'channel' => $channel,
            'recipient_user_id' => $userId,
            'locale' => $locale,
            'subject' => $this->safeSubject($this->render($template?->subject ?? $default['subject'], $payload)),
            'body' => $this->render($template?->body ?? $default['body'], $payload),
            'link' => $link,
            'state' => $channel === 'database' ? 'delivered' : 'pending',
            'idempotency_key' => $key,
            'template_version' => (int) ($template?->version ?? 0),
            'attempt_count' => 0,
            'max_attempts' => (int) config('exhibitions.notifications.max_attempts', 3),
            'safe_context' => json_encode($payload, JSON_THROW_ON_ERROR),
            'delivered_at' => $channel === 'database' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function safePayload(array $payload): array
    {
        $safe = [];

        foreach ($payload as $key => $value) {
            if (preg_match('/customer|phone|email|bank|payout|identity|document|secret|token|path/i', (string) $key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[$key] = $this->redact((string) $value);
            }
        }

        return $safe;
    }

    private function render(string $template, array $payload): string
    {
        $replace = [];

        foreach ($payload as $key => $value) {
            $replace['{{'.$key.'}}'] = (string) $value;
        }

        return $this->redact(strtr($template, $replace));
    }

    private function safeSubject(string $value): string
    {
        $subject = preg_replace('/[\r\n\x00-\x1F\x7F]+/u', ' ', $this->redact($value));
        $subject = mb_substr(trim((string) $subject), 0, 255);

        return $subject !== '' ? $subject : 'Exhibitions notification';
    }

    private function safeLink(?string $value): ?string
    {
        if ($value === null || $value === '' || preg_match('/[\x00-\x1F\x7F\\\\]/u', $value)) {
            return null;
        }

        $parts = parse_url($value);
        if (
            $parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        $decodedPath = rawurldecode($path);
        $allowed = $path === '/exhibitions'
            || str_starts_with($path, '/exhibitions/')
            || $path === '/admin/plugins/exhibitions'
            || str_starts_with($path, '/admin/plugins/exhibitions/');

        if (
            ! $allowed
            || preg_match('/[\x00-\x1F\x7F\\\\]/u', $decodedPath)
            || preg_match('#(?:^|/)\.\.(?:/|$)#', $decodedPath)
        ) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function redact(string $value): string
    {
        $value = strip_tags($value);
        $value = preg_replace('/\b[A-Z]{2}\d{2}[A-Z0-9]{10,30}\b/i', '[redacted]', $value);
        $value = preg_replace('/\+?\d[\d\s-]{7,}\d/', '[redacted]', (string) $value);

        return mb_substr((string) $value, 0, 5000);
    }

    private function isTransient(Throwable $exception): bool
    {
        if ($exception instanceof DomainException || $exception instanceof InvalidArgumentException) {
            return false;
        }

        return $exception instanceof TransportExceptionInterface || $exception instanceof RuntimeException;
    }

    private function retryMinutes(int $attempt): int
    {
        $configured = array_values((array) config('exhibitions.notifications.retry_minutes', [2, 4, 8]));
        $value = $configured[min(max(0, $attempt - 1), max(0, count($configured) - 1))] ?? 2 ** $attempt;

        return min(1440, max(1, (int) $value));
    }

    private function attemptRow(
        int $id,
        int $number,
        string $state,
        ?string $class = null,
        ?string $message = null,
    ): void {
        DB::table('exhibition_notification_attempts')->insert([
            'delivery_id' => $id,
            'attempt_number' => $number,
            'state' => $state,
            'failure_class' => $class,
            'failure_message' => $message,
            'attempted_at' => now(),
        ]);
    }

    private function find(string $id): object
    {
        return DB::table('exhibition_notification_deliveries')->where('public_id', $id)->first() ?? abort(404);
    }
}
