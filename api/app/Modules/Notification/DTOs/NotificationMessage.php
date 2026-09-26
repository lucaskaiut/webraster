<?php

namespace App\Modules\Notification\DTOs;

use App\Modules\Notification\Enums\NotificationSource;

readonly class NotificationMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $type,
        public string $title,
        public ?string $body = null,
        public array $data = [],
        public NotificationSource $source = NotificationSource::SYSTEM,
        public ?int $createdBy = null,
        public ?int $alertId = null,
        public bool $push = true,
        public bool $inApp = true,
    ) {}
}
