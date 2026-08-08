<?php

namespace App\Services\Ai;

use App\Enums\AiIntent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiUsageLimiter
{
    public function allowed(?User $user, AiIntent $intent): bool
    {
        if (! Schema::hasTable('ai_usage_logs') || $user === null) {
            return true;
        }

        $limit = (int) data_get(config('ai.daily_limits', []), $intent->value, 100);

        if ($limit <= 0) {
            return true;
        }

        $count = DB::table('ai_usage_logs')
            ->where('user_id', $user->id)
            ->where('intent', $intent->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return $count < $limit;
    }

    public function deniedReason(AiIntent $intent): string
    {
        return 'Daily AI limit reached for '.$intent->value.'.';
    }

    public function log(?User $user, AiIntent $intent, ?string $plugin, ?int $tokensUsed = null, ?float $costUnits = null): void
    {
        if (! Schema::hasTable('ai_usage_logs')) {
            return;
        }

        DB::table('ai_usage_logs')->insert([
            'user_id' => $user?->id,
            'intent' => $intent->value,
            'plugin' => $plugin,
            'tokens_used' => $tokensUsed,
            'cost_units' => $costUnits,
            'created_at' => now(),
        ]);
    }
}
