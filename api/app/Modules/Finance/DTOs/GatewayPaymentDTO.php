<?php

namespace App\Modules\Finance\DTOs;

use App\Modules\Finance\Enums\GatewayPaymentStatus;

final readonly class GatewayPaymentDTO
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $externalId,
        public GatewayPaymentStatus $status,
        public string $amount,
        public ?string $externalReference = null,
        public ?string $pixCode = null,
        public ?string $pixQrcode = null,
        public ?string $invoiceUrl = null,
        public ?string $bankSlipUrl = null,
        public ?array $metadata = null,
    ) {}

    public function amountCents(): int
    {
        return (int) round(((float) $this->amount) * 100);
    }
}
