<?php

namespace App\Modules\Finance\Gateways\Asaas;

use App\Modules\Finance\DTOs\GatewayPaymentDTO;
use App\Modules\Finance\Enums\GatewayPaymentStatus;

final class AsaasPaymentMapper
{
    public static function mapStatus(?string $asaasStatus): GatewayPaymentStatus
    {
        return match (strtoupper((string) $asaasStatus)) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => GatewayPaymentStatus::PAID,
            'OVERDUE' => GatewayPaymentStatus::OVERDUE,
            'REFUNDED',
            'REFUND_REQUESTED',
            'REFUND_IN_PROGRESS',
            'CHARGEBACK_REQUESTED',
            'CHARGEBACK_DISPUTE',
            'AWAITING_CHARGEBACK_REVERSAL' => GatewayPaymentStatus::REFUNDED,
            'AWAITING_RISK_ANALYSIS',
            'AUTHORIZED',
            'DUNNING_REQUESTED',
            'DUNNING_RECEIVED' => GatewayPaymentStatus::PROCESSING,
            'DELETED' => GatewayPaymentStatus::CANCELLED,
            'PENDING' => GatewayPaymentStatus::PENDING,
            default => GatewayPaymentStatus::PENDING,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function toGatewayPayment(array $payload): GatewayPaymentDTO
    {
        return new GatewayPaymentDTO(
            externalId: (string) ($payload['id'] ?? ''),
            status: self::mapStatus(isset($payload['status']) ? (string) $payload['status'] : null),
            amount: number_format((float) ($payload['value'] ?? $payload['totalValue'] ?? 0), 2, '.', ''),
            externalReference: isset($payload['externalReference']) ? (string) $payload['externalReference'] : null,
            pixCode: isset($payload['payload']) ? (string) $payload['payload'] : null,
            pixQrcode: isset($payload['encodedImage']) ? (string) $payload['encodedImage'] : null,
            invoiceUrl: isset($payload['invoiceUrl']) ? (string) $payload['invoiceUrl'] : null,
            bankSlipUrl: isset($payload['bankSlipUrl']) ? (string) $payload['bankSlipUrl'] : null,
            metadata: $payload,
        );
    }
}
