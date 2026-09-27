<?php

namespace App\Modules\Chat\Enums;

enum ChatType: string
{
    case INDIVIDUAL = 'individual';
    case GROUP = 'group';
}
