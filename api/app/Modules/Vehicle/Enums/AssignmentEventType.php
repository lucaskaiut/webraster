<?php

namespace App\Modules\Vehicle\Enums;

enum AssignmentEventType: string
{
    case INSTALLATION = 'installation';
    case REMOVAL = 'removal';
    case SWAP = 'swap';
}
