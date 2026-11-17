<?php

namespace App\Enums;

enum AiTaskType: string
{
    case Chat = 'chat';
    case Rag = 'rag';
    case Image = 'image';
    case FastImage = 'fast_image';
    case Vision = 'vision';
    case ArtworkSearch = 'artwork_search';
    case InternalAction = 'internal_action';
    case DataAccess = 'data_access';
    case Clarification = 'clarification';
}
