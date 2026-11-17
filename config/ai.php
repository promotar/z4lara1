<?php

use App\Enums\AiIntent;

return [
    'gateway_base_url' => env('AI_GATEWAY_BASE_URL', 'http://127.0.0.1:8080'),
    'gateway_api_key' => env('AI_GATEWAY_API_KEY', ''),
    'default_timeout' => (int) env('AI_DEFAULT_TIMEOUT', 60),
    'image_timeout' => (int) env('AI_IMAGE_TIMEOUT', 300),
    'fallback_classifier_enabled' => filter_var(env('AI_FALLBACK_CLASSIFIER_ENABLED', true), FILTER_VALIDATE_BOOL),
    'confidence_threshold' => (float) env('AI_INTENT_CONFIDENCE_THRESHOLD', 0.75),

    'enabled_intents' => [
        AiIntent::GeneralChat->value,
        AiIntent::RagQuestion->value,
        AiIntent::GenerateImage->value,
        AiIntent::FastGenerateImage->value,
        AiIntent::VisionAnalyze->value,
        AiIntent::ArtworkSimilarity->value,
        AiIntent::UpdateProfile->value,
        AiIntent::UpdateOrder->value,
        AiIntent::CodingAssistant->value,
        AiIntent::PlatformDataQuery->value,
        AiIntent::AdminReportQuery->value,
    ],

    'admin_only_intents' => [
        AiIntent::CodingAssistant->value,
        AiIntent::AdminReportQuery->value,
    ],

    'sensitive_intents' => [
        AiIntent::UpdateProfile->value,
        AiIntent::UpdateOrder->value,
    ],

    'daily_limits' => [
        AiIntent::GeneralChat->value => 100,
        AiIntent::RagQuestion->value => 100,
        AiIntent::GenerateImage->value => 4,
        AiIntent::FastGenerateImage->value => 10,
        AiIntent::VisionAnalyze->value => 20,
        AiIntent::ArtworkSimilarity->value => 20,
        AiIntent::CodingAssistant->value => 50,
        AiIntent::PlatformDataQuery->value => 50,
        AiIntent::AdminReportQuery->value => 30,
    ],
];
