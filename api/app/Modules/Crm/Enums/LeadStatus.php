<?php

namespace App\Modules\Crm\Enums;

enum LeadStatus: string
{
    case OPEN = 'open';
    case WON = 'won';
    case LOST = 'lost';
    case ARCHIVED = 'archived';
}
