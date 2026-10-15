<?php

namespace App\Modules\ContractEvidence\Enums;

enum SigningSessionStatus: string
{
    case OPEN = 'open';
    case COMPLETING = 'completing';
    case COMPLETED = 'completed';
    case EXPIRED = 'expired';
    case REVOKED = 'revoked';
}
