<?php

namespace App\Modules\Finance\Support;

final class FinanceNotificationMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly array $data = [],
        public readonly ?int $clientId = null,
        public readonly ?int $userId = null,
    ) {}
}
