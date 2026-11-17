<?php

namespace App\Services\Ai;

use App\Enums\AiIntent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AiConversationService
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function resolveConversation(?User $user, ?int $conversationId, ?string $plugin, array $metadata = []): int
    {
        if (! Schema::hasTable('ai_conversations')) {
            return 0;
        }

        if ($conversationId && $this->canUseConversation($user, $conversationId)) {
            return $conversationId;
        }

        return (int) DB::table('ai_conversations')->insertGetId([
            'user_id' => $user?->id,
            'plugin' => $plugin,
            'title' => null,
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param array<int, mixed> $attachments
     * @param array<string, mixed> $metadata
     */
    public function storeMessage(int $conversationId, ?User $user, string $role, ?AiIntent $intent, ?string $content, array $attachments = [], array $metadata = []): void
    {
        if ($conversationId <= 0 || ! Schema::hasTable('ai_messages')) {
            return;
        }

        DB::table('ai_messages')->insert([
            'conversation_id' => $conversationId,
            'user_id' => $user?->id,
            'role' => $role,
            'intent' => $intent?->value,
            'content' => $content,
            'attachments' => $attachments === [] ? null : json_encode($attachments, JSON_UNESCAPED_SLASHES),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('ai_conversations')->where('id', $conversationId)->update(['updated_at' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function routerState(int $conversationId): array
    {
        if ($conversationId <= 0 || ! Schema::hasTable('ai_conversations')) {
            return [];
        }

        $conversation = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->first(['metadata']);

        $metadata = [];

        if ($conversation && is_string($conversation->metadata)) {
            $decoded = json_decode($conversation->metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $recentMessages = [];

        if (Schema::hasTable('ai_messages')) {
            $recentMessages = DB::table('ai_messages')
                ->where('conversation_id', $conversationId)
                ->latest('id')
                ->limit(8)
                ->get(['role', 'intent', 'content', 'attachments', 'metadata'])
                ->reverse()
                ->map(fn (object $message): array => [
                    'role' => (string) $message->role,
                    'intent' => $message->intent ? (string) $message->intent : null,
                    'content' => (string) ($message->content ?? ''),
                    'attachments' => $this->decodeJsonColumn($message->attachments ?? null),
                    'metadata' => $this->decodeJsonColumn($message->metadata ?? null),
                ])
                ->values()
                ->all();
        }

        $metadata['recent_messages'] = $recentMessages;

        return $metadata;
    }

    public function rememberRouterState(int $conversationId, AiIntent $intent, string $userMessage, ?string $visualPrompt = null): void
    {
        if ($conversationId <= 0 || ! Schema::hasTable('ai_conversations')) {
            return;
        }

        $state = $this->routerState($conversationId);
        unset($state['recent_messages']);

        $state['last_router_intent'] = $intent->value;

        if ($intent === AiIntent::GenerateImage || $intent === AiIntent::FastGenerateImage) {
            $prompt = trim((string) ($visualPrompt ?: $userMessage));

            if ($prompt !== '') {
                $state['pending_intent'] = AiIntent::GenerateImage->value;
                $state['pending_visual_prompt'] = mb_substr($prompt, 0, 4000);
                $state['awaiting_visual_execution'] = true;
            }
        } elseif (! in_array($intent, [AiIntent::NeedsClarification, AiIntent::Unknown], true)) {
            $state['awaiting_visual_execution'] = false;
        }

        DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->update([
                'metadata' => json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    public function rememberToolResult(int $conversationId, array $result): void
    {
        if ($conversationId <= 0 || ! Schema::hasTable('ai_conversations')) {
            return;
        }

        $state = $this->routerState($conversationId);
        unset($state['recent_messages']);

        $result = array_filter([
            'id' => (string) ($result['id'] ?? (string) str()->uuid()),
            'type' => (string) ($result['type'] ?? 'tool_result'),
            'source' => (string) ($result['source'] ?? 'tool'),
            'url' => isset($result['url']) ? (string) $result['url'] : null,
            'storage_path' => isset($result['storage_path']) ? (string) $result['storage_path'] : null,
            'mime' => isset($result['mime']) ? (string) $result['mime'] : null,
            'name' => isset($result['name']) ? (string) $result['name'] : null,
            'prompt' => isset($result['prompt']) ? mb_substr((string) $result['prompt'], 0, 4000) : null,
            'tool' => isset($result['tool']) ? (string) $result['tool'] : null,
            'model' => isset($result['model']) ? (string) $result['model'] : null,
            'message_id' => $result['message_id'] ?? null,
            'intent' => isset($result['intent']) ? (string) $result['intent'] : null,
            'created_at' => (string) ($result['created_at'] ?? now()->toDateTimeString()),
            'user_id' => $result['user_id'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $results = is_array($state['tool_results'] ?? null) ? $state['tool_results'] : [];
        array_unshift($results, $result);
        $state['tool_results'] = array_slice($results, 0, 20);
        $state['last_tool_result'] = $result;

        if (in_array($result['type'] ?? '', ['image', 'vision_analysis', 'artwork_similarity'], true)) {
            $state['last_visual_result'] = $result;

            if (! empty($result['prompt'])) {
                $state['last_visual_prompt'] = $result['prompt'];
            }
        }

        DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->update([
                'metadata' => json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    private function canUseConversation(?User $user, int $conversationId): bool
    {
        $conversation = DB::table('ai_conversations')->where('id', $conversationId)->first(['user_id']);

        if (! $conversation) {
            return false;
        }

        if ($conversation->user_id === null) {
            return $user === null;
        }

        return $user !== null && (int) $conversation->user_id === (int) $user->id;
    }

    /**
     * @return array<string, mixed>|array<int, mixed>
     */
    private function decodeJsonColumn(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
