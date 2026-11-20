<?php

namespace Modules\Exhibition\Infrastructure\Persistence;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Exhibition\Contracts\Audit\AuditEntryData;
use Modules\Exhibition\Contracts\Audit\AuditRecorder;
use RuntimeException;

final class DatabaseAuditRecorder implements AuditRecorder
{
    public function record(AuditEntryData $entry): string
    {
        $table = (string) config('exhibitions.audit.table', 'exhibition_audit_entries');

        if (! Schema::hasTable($table)) {
            throw new RuntimeException('The Exhibitions audit table is not available.');
        }

        if ($entry->idempotencyKey !== null && Schema::hasColumn($table, 'idempotency_key')) {
            $existing = DB::table($table)->where('idempotency_key', $entry->idempotencyKey)->value('public_id');
            if ($existing !== null) {
                return (string) $existing;
            }
        }

        $publicId = (string) Str::uuid();
        $request = app()->bound('request') ? request() : null;
        $occurredAt = $entry->occurredAt ?? new DateTimeImmutable('now');

        $payload = [
            'public_id' => $publicId,
            'actor_user_id' => $entry->actorUserId ?? Auth::id(),
            'action' => $entry->action,
            'target_type' => $entry->targetType,
            'target_id' => $entry->targetId,
            'previous_values' => $this->json($entry->previousValues),
            'new_values' => $this->json($entry->newValues),
            'reason' => $entry->reason,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 1024, '') : null,
            'request_id' => $request?->headers->get('X-Request-Id'),
            'context' => $this->json($entry->context),
            'occurred_at' => $occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.u'),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($entry->idempotencyKey !== null && Schema::hasColumn($table, 'idempotency_key')) {
            $payload['idempotency_key'] = $entry->idempotencyKey;
            DB::table($table)->insertOrIgnore($payload);
            return (string) DB::table($table)->where('idempotency_key', $entry->idempotencyKey)->value('public_id');
        }
        DB::table($table)->insert($payload);

        return $publicId;
    }

    /** @param array<string, mixed>|null $value */
    private function json(?array $value): ?string
    {
        return $value === null ? null : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
