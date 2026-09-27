<?php

namespace App\Modules\Chat\Enums;

enum MessageType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case AUDIO = 'audio';
    case VIDEO = 'video';
    case DOCUMENT = 'document';
    case STICKER = 'sticker';
    case LOCATION = 'location';
    case CONTACT = 'contact';
    case REACTION = 'reaction';
    case POLL = 'poll';
    case SYSTEM = 'system';
    case UNKNOWN = 'unknown';
}
