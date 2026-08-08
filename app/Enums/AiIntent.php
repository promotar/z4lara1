<?php

namespace App\Enums;

enum AiIntent: string
{
    case GeneralChat = 'general_chat';
    case RagQuestion = 'rag_question';
    case GenerateImage = 'generate_image';
    case FastGenerateImage = 'fast_generate_image';
    case VisionAnalyze = 'vision_analyze';
    case ArtworkSimilarity = 'artwork_similarity';
    case UpdateProfile = 'update_profile';
    case UpdateOrder = 'update_order';
    case CodingAssistant = 'coding_assistant';
    case PlatformDataQuery = 'platform_data_query';
    case AdminReportQuery = 'admin_report_query';
    case Unknown = 'unknown';
    case NeedsClarification = 'needs_clarification';

    public function isSensitive(): bool
    {
        return in_array($this, [self::UpdateProfile, self::UpdateOrder], true);
    }

    public function isAiGatewayTask(): bool
    {
        return in_array($this, [
            self::GeneralChat,
            self::RagQuestion,
            self::GenerateImage,
            self::FastGenerateImage,
            self::VisionAnalyze,
            self::ArtworkSimilarity,
            self::CodingAssistant,
            self::PlatformDataQuery,
            self::AdminReportQuery,
        ], true);
    }
}
