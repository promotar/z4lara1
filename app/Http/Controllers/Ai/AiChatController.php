<?php

namespace App\Http\Controllers\Ai;

use App\Enums\AiIntent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AiMessageRequest;
use App\Services\Ai\AiActionExecutor;
use App\Services\Ai\AiConversationService;
use App\Services\Ai\AiDataAccessService;
use App\Services\Ai\AiGatewayClient;
use App\Services\Ai\AiIntentRouter;
use App\Services\Ai\AiPermissionChecker;
use App\Services\Ai\AiPromptSanitizer;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\AiUsageLimiter;
use Illuminate\Http\JsonResponse;
use Throwable;

class AiChatController extends Controller
{
    public function __construct(
        private readonly AiIntentRouter $router,
        private readonly AiPermissionChecker $permissions,
        private readonly AiUsageLimiter $usage,
        private readonly AiConversationService $conversations,
        private readonly AiPromptSanitizer $sanitizer,
        private readonly AiGatewayClient $gateway,
        private readonly AiActionExecutor $actions,
        private readonly AiDataAccessService $dataAccess,
        private readonly AiToolRegistry $tools,
    ) {
        //
    }

    public function message(AiMessageRequest $request): JsonResponse
    {
        $input = $request->validated();
        $user = $request->user();
        $plugin = isset($input['plugin']) ? (string) $input['plugin'] : null;
        $message = $this->sanitizer->sanitizeMessage((string) $input['message']);
        $attachments = $this->sanitizer->sanitizeAttachments(is_array($input['attachments'] ?? null) ? $input['attachments'] : []);
        $context = is_array($input['context'] ?? null) ? $input['context'] : [];

        $conversationId = $this->conversations->resolveConversation(
            $user,
            isset($input['conversation_id']) ? (int) $input['conversation_id'] : null,
            $plugin,
            ['source' => 'ai-router'],
        );

        $input['conversation_state'] = $this->conversations->routerState($conversationId);
        $result = $this->router->route($input);
        $intent = $result->intent;
        $effectiveMessage = $this->effectiveMessageForIntent($intent, $message, $result->data);

        if ($intent === AiIntent::NeedsClarification) {
            return response()->json([
                'ok' => true,
                'data' => [
                    'intent' => $intent->value,
                    'requires_confirmation' => false,
                    'message' => $result->message,
                ],
            ]);
        }

        if (! $this->permissions->canUseIntent($user, $intent)) {
            return response()->json([
                'ok' => false,
                'error' => $this->permissions->getDeniedReason($user, $intent),
                'intent' => $intent->value,
            ], 403);
        }

        if (! $this->usage->allowed($user, $intent)) {
            return response()->json([
                'ok' => false,
                'error' => $this->usage->deniedReason($intent),
                'intent' => $intent->value,
            ], 429);
        }

        $this->conversations->storeMessage($conversationId, $user, 'user', $intent, $message, $attachments);

        try {
            $response = $this->handleIntent($intent, $effectiveMessage, $plugin, $context, $attachments, $result->tool, $request);
        } catch (Throwable $exception) {
            $response = [
                'message' => 'AI service is currently unavailable. Please try again shortly.',
                'metadata' => ['error' => $exception->getMessage()],
                'endpoint_used' => null,
            ];
        }

        $assistantMessage = (string) ($response['message'] ?? 'Request completed.');
        $this->conversations->storeMessage($conversationId, $user, 'assistant', $intent, $assistantMessage, [], $response['metadata'] ?? []);
        $this->conversations->rememberRouterState($conversationId, $intent, $message, $effectiveMessage);
        $this->usage->log($user, $intent, $plugin);

        return response()->json([
            'ok' => true,
            'data' => [
                'intent' => $intent->value,
                'requires_confirmation' => (bool) ($response['requires_confirmation'] ?? false),
                'message' => $assistantMessage,
                'endpoint_used' => $response['endpoint_used'] ?? null,
                'conversation_id' => $conversationId,
                'data' => $response['data'] ?? null,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $attachments
     * @return array<string, mixed>
     */
    private function handleIntent(AiIntent $intent, string $message, ?string $plugin, array $context, array $attachments, ?string $tool, AiMessageRequest $request): array
    {
        if ($intent->isSensitive()) {
            if (! (bool) data_get($context, 'confirmed', false)) {
                return [
                    'message' => $this->confirmationMessage($intent, $context),
                    'requires_confirmation' => true,
                    'data' => $context['data'] ?? [],
                    'endpoint_used' => null,
                ];
            }

            $action = $this->actions->execute($request->user(), $intent, is_array($context['data'] ?? null) ? $context['data'] : []);

            return [
                'message' => (string) ($action['message'] ?? 'Action completed.'),
                'data' => $action['data'] ?? null,
                'endpoint_used' => 'laravel:action-executor',
            ];
        }

        if (in_array($intent, [AiIntent::PlatformDataQuery, AiIntent::AdminReportQuery], true)) {
            $tool = $tool ?: $this->tools->toolForMessage($message);

            if (! $tool) {
                return [
                    'message' => 'This platform data request does not match a registered AI data tool. Please ask for an allowed report such as users registered in the last 24 hours or your own profile.',
                    'endpoint_used' => 'laravel:data-tool-not-registered',
                ];
            }

            $data = $this->dataAccess->execute($tool, $request->user(), $intent, $request, [
                'message' => $message,
                'plugin' => $plugin,
            ]);

            if (! ($data['ok'] ?? false)) {
                return [
                    'message' => (string) ($data['error'] ?? 'You do not have permission to access this data.'),
                    'endpoint_used' => 'laravel:data-access-denied',
                ];
            }

            $gateway = $this->gateway->chatGeneral([
                'message' => $message,
                'system' => 'Answer only using the provided authorized data. Do not invent data.',
                'authorized_data' => $data['data'],
                'plugin' => $plugin,
            ]);

            return [
                'message' => $this->extractGatewayMessage($gateway),
                'data' => $data['data'],
                'metadata' => ['tool' => $tool],
                'endpoint_used' => '/v1/general/chat',
            ];
        }

        return match ($intent) {
            AiIntent::RagQuestion => $this->handleRag($message, $plugin, $context, $attachments),
            AiIntent::GenerateImage => $this->gatewayResponse($this->gateway->generateImage($this->imagePayload($message, $plugin, $context, $attachments)), '/v1/images/generate'),
            AiIntent::FastGenerateImage => $this->gatewayResponse($this->gateway->generateFastImage($this->imagePayload($message, $plugin, $context, $attachments)), '/v1/images/fast-generate'),
            AiIntent::VisionAnalyze => $this->gatewayResponse($this->gateway->analyzeVision($this->visionPayload($message, $plugin, $context, $attachments)), '/v1/vision/analyze'),
            AiIntent::ArtworkSimilarity => $this->gatewayResponse($this->gateway->searchArtwork($this->artworkPayload($message, $plugin, $context, $attachments)), '/v1/artwork/search'),
            AiIntent::CodingAssistant => $this->gatewayResponse($this->gateway->chatCoding($this->basePayload($message, $plugin, $context, $attachments)), '/v1/coding/chat'),
            default => $this->gatewayResponse($this->gateway->chatGeneral($this->basePayload($message, $plugin, $context, $attachments)), '/v1/general/chat'),
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function handleRag(string $message, ?string $plugin, array $context, array $attachments): array
    {
        $textualAttachments = $this->textualAttachmentPayload($attachments);

        if ($textualAttachments !== []) {
            $chat = $this->gateway->chatGeneral([
                'message' => $message,
                'plugin' => $plugin,
                'context' => $context,
                'system' => 'Answer only using the provided authorized attachment excerpts. Do not invent file contents.',
                'attachments' => $textualAttachments,
            ]);

            return $this->gatewayResponse($chat, '/v1/general/chat (attachment context)');
        }

        $rag = $this->gateway->ragSearch([
            'message' => $message,
            'plugin' => $plugin,
            'context' => $context,
        ]);

        $chat = $this->gateway->chatGeneral([
            'message' => $message,
            'plugin' => $plugin,
            'context' => $context,
            'rag_results' => $rag['data'] ?? $rag,
        ]);

        return $this->gatewayResponse($chat, '/v1/rag/search + /v1/general/chat', ['rag' => $rag]);
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, array<string, mixed>> $attachments
     * @return array<string, mixed>
     */
    private function basePayload(string $message, ?string $plugin, array $context, array $attachments): array
    {
        return [
            'message' => $message,
            'plugin' => $plugin,
            'context' => $context,
            'attachments' => $attachments,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, mixed> $attachments
     * @return array<string, mixed>
     */
    private function imagePayload(string $prompt, ?string $plugin, array $context, array $attachments): array
    {
        $payload = $this->basePayload($prompt, $plugin, $context, $attachments);
        $payload['prompt'] = $prompt;

        return $payload;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, mixed> $attachments
     * @return array<string, mixed>
     */
    private function visionPayload(string $message, ?string $plugin, array $context, array $attachments): array
    {
        $payload = $this->basePayload($message, $plugin, $context, $attachments);
        $payload['question'] = $message;
        $payload['mode'] = $this->isOcrQuestion($message) ? 'ocr' : 'artwork_review';

        if ($image = $this->firstImageAttachment($attachments)) {
            if (! empty($image['image_base64'])) {
                $payload['image_base64'] = $image['image_base64'];
            }

            if (! empty($image['url'])) {
                $payload['image_url'] = $image['url'];
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<int, mixed> $attachments
     * @return array<string, mixed>
     */
    private function artworkPayload(string $message, ?string $plugin, array $context, array $attachments): array
    {
        $payload = $this->basePayload($message, $plugin, $context, $attachments);
        $payload['text_query'] = $message;

        if ($image = $this->firstImageAttachment($attachments)) {
            if (! empty($image['image_base64'])) {
                $payload['image_base64'] = $image['image_base64'];
            }

            if (! empty($image['url'])) {
                $payload['image_url'] = $image['url'];
            }
        }

        return $payload;
    }

    /**
     * @param array<int, mixed> $attachments
     * @return array<string, mixed>|null
     */
    private function firstImageAttachment(array $attachments): ?array
    {
        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $mime = strtolower((string) ($attachment['mime'] ?? $attachment['mime_type'] ?? ''));

            if (($attachment['type'] ?? '') === 'image' || str_starts_with($mime, 'image/')) {
                return $attachment;
            }
        }

        return null;
    }

    private function isOcrQuestion(string $message): bool
    {
        $message = mb_strtolower(str_replace(['أ', 'إ', 'آ'], 'ا', $message));

        foreach (['ocr', 'اقرا النص', 'اقرأ النص', 'النص الموجود', 'استخرج النص', 'read text', 'extract text'] as $needle) {
            if (str_contains($message, mb_strtolower(str_replace(['أ', 'إ', 'آ'], 'ا', $needle)))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $attachments
     * @return array<int, array<string, mixed>>
     */
    private function textualAttachmentPayload(array $attachments): array
    {
        return array_values(array_filter(array_map(function (mixed $attachment): ?array {
            if (! is_array($attachment)) {
                return null;
            }

            $text = trim((string) ($attachment['text_excerpt'] ?? ''));

            if ($text === '') {
                return null;
            }

            return [
                'name' => $attachment['name'] ?? 'attachment',
                'mime' => $attachment['mime'] ?? null,
                'text_excerpt' => $text,
            ];
        }, $attachments)));
    }

    /**
     * @param array<string, mixed> $gateway
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function gatewayResponse(array $gateway, string $endpoint, array $metadata = []): array
    {
        return [
            'message' => $this->extractGatewayMessage($gateway),
            'data' => $gateway['data'] ?? null,
            'metadata' => $metadata,
            'endpoint_used' => $endpoint,
        ];
    }

    /**
     * @param array<string, mixed> $gateway
     */
    private function extractGatewayMessage(array $gateway): string
    {
        foreach ([
            data_get($gateway, 'data.message'),
            data_get($gateway, 'data.response'),
            data_get($gateway, 'message'),
            data_get($gateway, 'response'),
            data_get($gateway, 'result'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return 'The AI request completed.';
    }

    /**
     * @param array<string, mixed> $context
     */
    private function confirmationMessage(AiIntent $intent, array $context): string
    {
        if ($intent === AiIntent::UpdateProfile) {
            $field = (string) data_get($context, 'data.field', 'profile');
            $value = (string) data_get($context, 'data.value', '');

            return $value !== ''
                ? "Please confirm changing your {$field} to {$value}."
                : "Please confirm updating your {$field}.";
        }

        return 'Please confirm this sensitive action before Laravel executes it.';
    }

    /**
     * @param array<string, mixed> $intentData
     */
    private function effectiveMessageForIntent(AiIntent $intent, string $message, array $intentData): string
    {
        if ($intent !== AiIntent::GenerateImage && $intent !== AiIntent::FastGenerateImage) {
            return $message;
        }

        $visualPrompt = trim((string) ($intentData['visual_prompt'] ?? ''));

        return $visualPrompt !== '' ? $visualPrompt : $message;
    }
}
