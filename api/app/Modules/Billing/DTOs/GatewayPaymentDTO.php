<?php

namespace App\Modules\Billing\DTOs;

use App\Modules\Billing\Enums\GatewayPaymentStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final readonly class GatewayPaymentDTO
{
    public function __construct(
        public string $externalId,
        public GatewayPaymentStatus $status,
        public string $amount,
        public ?string $pixCode = null,
        public ?string $pixQrcode = null,
        public ?CarbonInterface $expiresAt = null,
        public ?array $metadata = null,
    ) {}

    /**
     * @param  array{external_id: string, status: string, amount: string|float|int, pix_code?: ?string, pix_qrcode?: ?string, expires_at?: CarbonInterface|string|null, metadata?: ?array}  $data
     */
    public static function fromArray(array $data): self
    {
        $expiresAt = null;

        if (isset($data['expires_at']) && $data['expires_at'] !== null) {
            $expiresAt = $data['expires_at'] instanceof CarbonInterface
                ? $data['expires_at']
                : CarbonImmutable::parse($data['expires_at']);
        }

        return new self(
            externalId: $data['external_id'],
            status: GatewayPaymentStatus::from($data['status']),
            amount: number_format((float) $data['amount'], 2, '.', ''),
            pixCode: $data['pix_code'] ?? null,
            pixQrcode: $data['pix_qrcode'] ?? null,
            expiresAt: $expiresAt,
            metadata: $data['metadata'] ?? null,
        );
    }
}
