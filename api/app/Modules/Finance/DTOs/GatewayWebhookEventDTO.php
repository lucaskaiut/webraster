<?php

namespace App\Modules\Finance\DTOs;

use App\Modules\Finance\Enums\GatewayWebhookEventType;

final readonly class GatewayWebhookEventDTO
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public GatewayWebhookEventType $type,
        public ?string $eventId,
        public ?string $paymentExternalId,
        public ?string $billingExternalReference,
        public array $payload,
    ) {}
}
