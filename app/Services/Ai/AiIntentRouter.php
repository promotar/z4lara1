<?php

namespace App\Services\Ai;

use App\Data\AiIntentResult;
use App\Enums\AiIntent;
use Throwable;

class AiIntentRouter
{
    public function __construct(
        private readonly AiGatewayClient $gateway,
        private readonly AiPromptSanitizer $sanitizer,
        private readonly AiToolRegistry $tools,
    ) {
        //
    }

    /**
     * @param array<string, mixed> $input
     */
    public function route(array $input): AiIntentResult
    {
        $message = $this->sanitizer->sanitizeMessage((string) ($input['message'] ?? ''));
        $plugin = trim((string) ($input['plugin'] ?? ''));
        $action = trim((string) ($input['action'] ?? ''));
        $attachments = is_array($input['attachments'] ?? null) ? $input['attachments'] : [];
        $conversationState = is_array($input['conversation_state'] ?? null) ? $input['conversation_state'] : [];
        $hasImage = $this->sanitizer->hasImage($attachments);
        $hasDocument = $this->hasDocument($attachments);

        if ($intent = $this->fromExplicitAction($action)) {
            return new AiIntentResult($intent, reason: 'explicit UI action');
        }

        if ($this->isDirectFastImageCommand($message)) {
            return new AiIntentResult(AiIntent::FastGenerateImage, reason: 'hard match fast image keyword', data: [
                'visual_prompt' => $message,
            ]);
        }

        if ($this->isDirectImageGenerationCommand($message)) {
            return new AiIntentResult(AiIntent::GenerateImage, reason: 'hard match image generation keyword', data: [
                'visual_prompt' => $message,
            ]);
        }

        if ($followUp = $this->resolveFollowUpIntent($message, $conversationState)) {
            return $followUp;
        }

        if ($visualContext = $this->resolveVisualContextIntent($message, $conversationState)) {
            return $visualContext;
        }

        if ($tool = $this->tools->toolForMessage($message)) {
            return new AiIntentResult(AiIntent::PlatformDataQuery, reason: 'platform data query keyword', tool: $tool);
        }

        if ($this->containsAny($message, $this->platformDataKeywords())) {
            return new AiIntentResult(AiIntent::PlatformDataQuery, reason: 'platform data query without a registered tool');
        }

        if ($hasDocument) {
            return new AiIntentResult(AiIntent::RagQuestion, reason: 'uploaded document context');
        }

        if ($hasImage && $this->containsAny($message, $this->artworkSimilarityKeywords())) {
            return new AiIntentResult(AiIntent::ArtworkSimilarity, reason: 'image plus artwork similarity keyword');
        }

        if ($hasImage && ($message === '' || $this->containsAny($message, $this->visionKeywords()))) {
            return new AiIntentResult(AiIntent::VisionAnalyze, reason: 'uploaded image analysis');
        }

        if ($intent = $this->fromPluginContext($plugin, $message)) {
            return new AiIntentResult($intent, reason: 'plugin context');
        }

        if ($this->containsAny($message, $this->visionKeywords())) {
            return new AiIntentResult(AiIntent::VisionAnalyze, reason: 'vision keyword');
        }

        if ($this->containsAny($message, $this->artworkSimilarityKeywords())) {
            return new AiIntentResult(AiIntent::ArtworkSimilarity, reason: 'artwork similarity keyword');
        }

        if ($this->containsAny($message, $this->profileUpdateKeywords())) {
            return $this->profileUpdateResult($message);
        }

        if ($this->containsAny($message, $this->ragKeywords())) {
            return new AiIntentResult(AiIntent::RagQuestion, reason: 'knowledge keyword');
        }

        if ($this->containsAny($message, $this->codingKeywords())) {
            return new AiIntentResult(AiIntent::CodingAssistant, reason: 'coding keyword');
        }

        if ((bool) config('ai.fallback_classifier_enabled', true)) {
            return $this->fallbackClassify($message, $plugin, $input, $hasImage);
        }

        return new AiIntentResult(AiIntent::GeneralChat, reason: 'default general chat');
    }

    /**
     * @param array<string, mixed> $conversationState
     */
    public function resolveFollowUpIntent(string $message, array $conversationState): ?AiIntentResult
    {
        if (! $this->isVisualFollowUpCommand($message)) {
            return null;
        }

        if (! $this->stateHasVisualPrompt($conversationState)) {
            return null;
        }

        $visualPrompt = $this->visualPromptFromState($conversationState);

        if ($visualPrompt === '') {
            return null;
        }

        return new AiIntentResult(
            intent: AiIntent::GenerateImage,
            reason: 'follow-up visual execution from conversation state',
            data: ['visual_prompt' => $visualPrompt],
        );
    }

    /**
     * @param array<string, mixed> $conversationState
     */
    public function resolveVisualContextIntent(string $message, array $conversationState): ?AiIntentResult
    {
        if (! $this->stateHasVisualResult($conversationState)) {
            return null;
        }

        if ($this->isVisionContextCommand($message)) {
            return new AiIntentResult(
                intent: AiIntent::VisionAnalyze,
                reason: 'visual context follow-up from last tool result',
                data: ['use_last_visual_result' => true],
            );
        }

        if ($this->isVisualRegenerationCommand($message)) {
            $visualPrompt = $this->visualPromptFromState($conversationState);

            if ($visualPrompt === '') {
                $visualPrompt = trim((string) ($conversationState['last_visual_prompt'] ?? ''));
            }

            if ($visualPrompt !== '') {
                return new AiIntentResult(
                    intent: AiIntent::GenerateImage,
                    reason: 'visual regeneration follow-up from last tool result',
                    data: ['visual_prompt' => $visualPrompt],
                );
            }
        }

        return null;
    }

    private function fromExplicitAction(string $action): ?AiIntent
    {
        return match ($action) {
            'generate_image' => AiIntent::GenerateImage,
            'fast_generate_image' => AiIntent::FastGenerateImage,
            'analyze_image' => AiIntent::VisionAnalyze,
            'search_artwork' => AiIntent::ArtworkSimilarity,
            'search_site' => AiIntent::PlatformDataQuery,
            'search_database' => AiIntent::PlatformDataQuery,
            'coding_assistant' => AiIntent::CodingAssistant,
            default => null,
        };
    }

    private function fromPluginContext(string $plugin, string $message): ?AiIntent
    {
        return match ($plugin) {
            'courses' => AiIntent::RagQuestion,
            'gallery' => $this->containsAny($message, $this->artworkSimilarityKeywords()) ? AiIntent::ArtworkSimilarity : AiIntent::VisionAnalyze,
            'page_builder' => AiIntent::GenerateImage,
            'admin_developer' => AiIntent::CodingAssistant,
            'store' => $this->containsAny($message, $this->ragKeywords()) ? AiIntent::RagQuestion : AiIntent::GeneralChat,
            default => null,
        };
    }

    private function fallbackClassify(string $message, string $plugin, array $input, bool $hasImage): AiIntentResult
    {
        try {
            $response = $this->gateway->classifyIntent([
                'message' => $message,
                'plugin' => $plugin,
                'context' => $input['context'] ?? [],
                'has_image' => $hasImage,
                'allowed_intents' => config('ai.enabled_intents', []),
            ]);
        } catch (Throwable) {
            return new AiIntentResult(AiIntent::GeneralChat, confidence: 0.5, reason: 'fallback classifier unavailable');
        }

        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $confidence = (float) ($data['confidence'] ?? 0);
        $intent = AiIntent::tryFrom((string) ($data['intent'] ?? 'unknown')) ?? AiIntent::Unknown;

        if ($confidence < (float) config('ai.confidence_threshold', 0.75)) {
            return AiIntentResult::clarification('low fallback classifier confidence');
        }

        return new AiIntentResult(
            intent: $intent,
            confidence: $confidence,
            requiresConfirmation: (bool) ($data['requires_confirmation'] ?? $intent->isSensitive()),
            reason: (string) ($data['reason'] ?? 'fallback classifier'),
            data: is_array($data['data'] ?? null) ? $data['data'] : [],
            tool: isset($data['tool']) ? (string) $data['tool'] : null,
        );
    }

    private function profileUpdateResult(string $message): AiIntentResult
    {
        $field = str_contains($message, 'email') || str_contains($message, 'ايميل') || str_contains($message, 'الإيميل')
            ? 'email'
            : (str_contains($message, 'رقمي') || str_contains($message, 'phone') ? 'phone' : 'name');

        return new AiIntentResult(
            intent: AiIntent::UpdateProfile,
            requiresConfirmation: true,
            reason: 'profile update keyword',
            message: 'Please confirm updating your profile.',
            data: ['field' => $field],
        );
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $message, array $needles): bool
    {
        $message = $this->normalizeText($message);

        foreach ($needles as $needle) {
            if (str_contains($message, $this->normalizeText($needle))) {
                return true;
            }
        }

        return false;
    }

    private function isDirectFastImageCommand(string $message): bool
    {
        return $this->isDirectImageGenerationCommand($message)
            && $this->containsAny($message, ['صورة سريعة', 'سريع', 'سريعة', 'quick image', 'fast image', 'draft image', 'quick', 'fast', 'draft']);
    }

    private function isDirectImageGenerationCommand(string $message): bool
    {
        if ($this->containsAny($message, $this->imageKeywords())) {
            return true;
        }

        $hasAction = $this->containsAny($message, [
            'اعمل',
            'اعملي',
            'اعمللي',
            'اعمل لى',
            'اعمل لي',
            'صمم',
            'صمملي',
            'صمم لي',
            'سوي',
            'سويلي',
            'اصنع',
            'اصنعلي',
            'انشئ',
            'أنشئ',
            'انشا',
            'أنشا',
            'ولد',
            'ولّد',
            'ارسم',
            'ارسملي',
            'نفذ',
            'create',
            'generate',
            'design',
            'make',
            'draw',
        ]);

        $hasVisualTarget = $this->containsAny($message, [
            'صورة',
            'صوره',
            'بوستر',
            'اعلان',
            'إعلان',
            'تصميم',
            'رسمة',
            'رسمه',
            'رسم',
            'image',
            'picture',
            'photo',
            'poster',
            'design',
            'artwork',
        ]);

        return $hasAction && $hasVisualTarget;
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['أ', 'إ', 'آ'], 'ا', $value);
        $value = str_replace(['ى'], 'ي', $value);
        $value = str_replace(['ة'], 'ه', $value);
        $value = str_replace(['ؤ'], 'و', $value);
        $value = str_replace(['ئ'], 'ي', $value);
        $value = preg_replace('/[ًٌٍَُِّْـ]/u', '', $value) ?? $value;
        $value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function isVisualFollowUpCommand(string $message): bool
    {
        $message = trim(mb_strtolower($message));

        if ($message === '' || mb_strlen($message) > 80) {
            return false;
        }

        return $this->containsAny($message, [
            'صمم الصورة',
            'صممها',
            'صمم انت هذه الصورة',
            'صمم أنت هذه الصورة',
            'اعملها',
            'نفذ',
            'ابدأ',
            'إبدأ',
            'ابدأ التنفيذ',
            'نعم',
            'سويها',
            'نفذها',
            'اصنعها',
            'اعمل التصميم',
            'design it',
            'create it',
            'make it',
            'yes',
            'start',
            'do it',
        ]);
    }

    private function isVisionContextCommand(string $message): bool
    {
        $message = trim(mb_strtolower($message));

        if ($message === '' || mb_strlen($message) > 120) {
            return false;
        }

        return $this->containsAny($message, [
            'حللها',
            'حلل الصورة',
            'حلل هاي الصورة',
            'شو فيها',
            'شو في الصورة',
            'وين الفنان',
            'اين الفنان',
            'صفها',
            'اشرح الصورة',
            'اقرا النص',
            'اقرأ النص',
            'analyze it',
            'analyze this',
            'describe it',
            'what is in it',
            'where is the artist',
            'read the text',
        ]);
    }

    private function isVisualRegenerationCommand(string $message): bool
    {
        $message = trim(mb_strtolower($message));

        if ($message === '' || mb_strlen($message) > 120) {
            return false;
        }

        return $this->containsAny($message, [
            'ارسمها مرة ثانية',
            'اعملها مرة ثانية',
            'اعمل نفس الصورة',
            'اعد توليدها',
            'أعد توليدها',
            'الصورة مش صحيحة',
            'الصورة غلط',
            'جرب مرة ثانية',
            'regenerate it',
            'try again',
            'make it again',
            'same image again',
        ]);
    }

    /**
     * @param array<string, mixed> $conversationState
     */
    private function stateHasVisualPrompt(array $conversationState): bool
    {
        if (($conversationState['pending_intent'] ?? null) === AiIntent::GenerateImage->value) {
            return true;
        }

        if (($conversationState['last_router_intent'] ?? null) === AiIntent::GenerateImage->value) {
            return true;
        }

        foreach (($conversationState['recent_messages'] ?? []) as $message) {
            if (! is_array($message)) {
                continue;
            }

            if (($message['intent'] ?? null) === AiIntent::GenerateImage->value) {
                return true;
            }

            $content = (string) ($message['content'] ?? '');

            if ($this->containsAny($content, array_merge($this->imageKeywords(), $this->visualDescriptionKeywords()))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $conversationState
     */
    private function stateHasVisualResult(array $conversationState): bool
    {
        $lastVisual = is_array($conversationState['last_visual_result'] ?? null)
            ? $conversationState['last_visual_result']
            : [];

        if ($lastVisual !== [] && in_array(($lastVisual['type'] ?? null), ['image', 'vision_analysis', 'artwork_similarity'], true)) {
            return true;
        }

        foreach (($conversationState['tool_results'] ?? []) as $result) {
            if (! is_array($result)) {
                continue;
            }

            if (in_array(($result['type'] ?? null), ['image', 'vision_analysis', 'artwork_similarity'], true)) {
                return true;
            }
        }

        foreach (($conversationState['recent_messages'] ?? []) as $message) {
            if (! is_array($message)) {
                continue;
            }

            $metadata = is_array($message['metadata'] ?? null) ? $message['metadata'] : [];
            $attachments = is_array($message['attachments'] ?? null) ? $message['attachments'] : [];

            if (is_array($metadata['images'] ?? null) && $metadata['images'] !== []) {
                return true;
            }

            foreach ($attachments as $attachment) {
                if (! is_array($attachment)) {
                    continue;
                }

                $mime = strtolower((string) ($attachment['mime'] ?? ''));

                if (($attachment['type'] ?? null) === 'image' || str_starts_with($mime, 'image/')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $conversationState
     */
    private function visualPromptFromState(array $conversationState): string
    {
        $pending = trim((string) ($conversationState['pending_visual_prompt'] ?? ''));

        if ($pending !== '') {
            return $pending;
        }

        $messages = array_reverse(is_array($conversationState['recent_messages'] ?? null) ? $conversationState['recent_messages'] : []);

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $content = trim((string) ($message['content'] ?? ''));

            if ($content !== '' && ($message['role'] ?? '') === 'user' && $this->containsAny($content, array_merge($this->imageKeywords(), $this->visualDescriptionKeywords()))) {
                return mb_substr($content, 0, 4000);
            }
        }

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $content = trim((string) ($message['content'] ?? ''));

            if ($content !== '' && $this->containsAny($content, $this->visualDescriptionKeywords())) {
                return mb_substr($content, 0, 4000);
            }
        }

        return '';
    }

    private function imageKeywords(): array
    {
        return [
            'اعمل صورة',
            'اعمللي صورة',
            'صمم صورة',
            'صمملي صورة',
            'ولد صورة',
            'ولّد صورة',
            'انشئ صورة',
            'أنشئ صورة',
            'بوستر',
            'اعلان',
            'إعلان',
            'poster',
            'generate image',
            'create image',
            'design image',
        ];
    }

    private function fastImageKeywords(): array
    {
        return ['صورة سريعة', 'quick image', 'fast image', 'draft image'];
    }

    private function visionKeywords(): array
    {
        return ['حلل الصورة', 'شو في الصورة', 'قيّم اللوحة', 'قيم اللوحة', 'describe image', 'analyze image', 'ocr', 'اقرأ النص'];
    }

    private function artworkSimilarityKeywords(): array
    {
        return ['تشابه', 'منسوخة', 'أصلية', 'اصلية', 'فريدة', 'uniqueness', 'similar artwork', 'copied', 'plagiarism'];
    }

    private function profileUpdateKeywords(): array
    {
        return ['غير اسمي', 'عدل اسمي', 'غير رقمي', 'عدل الايميل', 'update my name', 'change my email'];
    }

    private function ragKeywords(): array
    {
        return ['سياسة', 'شروط', 'كورس', 'مقال', 'محتوى المنصة', 'knowledge base', 'policy', 'course', 'article'];
    }

    private function codingKeywords(): array
    {
        return ['كود', 'برمجة', 'laravel', 'php', 'plugin', 'migration', 'controller', 'code'];
    }

    private function platformDataKeywords(): array
    {
        return [
            'جدول',
            'داتابيس',
            'قاعدة البيانات',
            'ابحث في الموقع',
            'بحث في الموقع',
            'فتش في الموقع',
            'فتش في الصفحات',
            'ابحث في الصفحات',
            'معلومة في الموقع',
            'المقالات',
            'الصفحات',
            'الرولات',
            'الأدوار',
            'ادوار المستخدمين',
            'كل المستخدمين',
            'table',
            'database',
            'platform data',
            'raw sql',
            'search the site',
            'site search',
            'search pages',
            'search database',
            'roles list',
            'all users',
        ];
    }

    private function visualDescriptionKeywords(): array
    {
        return [
            'صورة',
            'تصميم',
            'بوستر',
            'خلفية',
            'إضاءة',
            'اضاءة',
            'ألوان',
            'الوان',
            'طفل',
            'غابة',
            'معرض فني',
            'poster',
            'image',
            'visual',
            'design',
            'background',
            'lighting',
        ];
    }

    /**
     * @param array<int, mixed> $attachments
     */
    private function hasDocument(array $attachments): bool
    {
        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $type = strtolower((string) ($attachment['type'] ?? ''));
            $mime = strtolower((string) ($attachment['mime'] ?? $attachment['mime_type'] ?? ''));

            if ($type === 'document' || ($mime !== '' && ! str_starts_with($mime, 'image/'))) {
                return true;
            }
        }

        return false;
    }
}
