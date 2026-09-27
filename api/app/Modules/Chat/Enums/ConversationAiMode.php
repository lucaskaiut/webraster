<?php

namespace App\Modules\Chat\Enums;

enum ConversationAiMode: string
{
    case AI_ACTIVE = 'ai_active';
    case AI_DISABLED = 'ai_disabled';
    case HUMAN_HANDOFF = 'human_handoff';
}
