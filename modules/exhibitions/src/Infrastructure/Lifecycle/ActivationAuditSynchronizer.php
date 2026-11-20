<?php

namespace Modules\Exhibition\Infrastructure\Lifecycle;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Records each real active-runtime epoch once. The Core activation service
 * persists `activated_at`; this module consumes that stable state without
 * replacing or modifying the shared lifecycle service.
 */
final class ActivationAuditSynchronizer
{
    public function sync(): void
    {
        if (
            ! Schema::hasTable('plugins')
            || ! Schema::hasTable('exhibition_audit_entries')
            || ! Schema::hasColumn('exhibition_audit_entries', 'idempotency_key')
        ) {
            return;
        }

        $plugin = DB::table('plugins')
            ->where('slug', 'exhibitions')
            ->where('status', 'active')
            ->first(['activated_at']);

        if ($plugin === null || $plugin->activated_at === null) {
            return;
        }

        $activatedAt = (string) $plugin->activated_at;
        $fingerprint = hash('sha256', 'exhibitions|'.$activatedAt);

        $now = now();
        DB::table('exhibition_audit_entries')->insertOrIgnore([
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => 'runtime-active:'.$fingerprint,
            'actor_user_id' => null,
            'action' => 'exhibitions.lifecycle.runtime_active',
            'target_type' => 'module',
            'target_id' => 'exhibitions',
            'previous_values' => null,
            'new_values' => json_encode([
                'status' => 'active',
                'activated_at' => $activatedAt,
                'version' => (string) config('exhibitions.version', '1.6.7'),
            ], JSON_THROW_ON_ERROR),
            'reason' => 'Active Exhibitions runtime registered by the platform.',
            'ip_address' => null,
            'user_agent' => null,
            'request_id' => null,
            'context' => json_encode([
                'activation_fingerprint' => $fingerprint,
                'source' => 'provider_boot',
            ], JSON_THROW_ON_ERROR),
            'occurred_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
