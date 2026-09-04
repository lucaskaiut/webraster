<?php

namespace App\Modules\Billing\Gateways\Asaas;

use App\Modules\Billing\DTOs\GatewayPaymentDTO;
use App\Modules\Billing\Enums\GatewayPaymentStatus;
use Carbon\CarbonImmutable;

final class AsaasPaymentMapper
{
    public static function mapStatus(?string $asaasStatus): GatewayPaymentStatus
    {
        return match (strtoupper((string) $asaasStatus)) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => GatewayPaymentStatus::PAID,
            'OVERDUE' => GatewayPaymentStatus::EXPIRED,
            'REFUNDED',
            'REFUND_REQUESTED',
            'REFUND_IN_PROGRESS',
            'CHARGEBACK_REQUESTED',
            'CHARGEBACK_DISPUTE',
            'AWAITING_CHARGEBACK_REVERSAL' => GatewayPaymentStatus::CANCELLED,
            'AWAITING_RISK_ANALYSIS',
            'AUTHORIZED',
            'DUNNING_REQUESTED',
            'DUNNING_RECEIVED' => GatewayPaymentStatus::PROCESSING,
            'PENDING' => GatewayPaymentStatus::PENDING,
            default => GatewayPaymentStatus::PENDING,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $extraMetadata
     */
    public static function toGatewayPayment(array $payload, ?array $extraMetadata = null): GatewayPaymentDTO
    {
        $creditCard = is_array($payload['creditCard'] ?? null) ? $payload['creditCard'] : [];

        $metadata = array_filter([
            'invoice_url' => $payload['invoiceUrl'] ?? null,
            'invoice_number' => $payload['invoiceNumber'] ?? null,
            'billing_type' => $payload['billingType'] ?? null,
            'asaas_status' => $payload['status'] ?? null,
            'transaction_receipt_url' => $payload['transactionReceiptUrl'] ?? null,
            'credit_card_brand' => $creditCard['creditCardBrand'] ?? null,
            'credit_card_number' => $creditCard['creditCardNumber'] ?? null,
            'credit_card_token' => $creditCard['creditCardToken'] ?? null,
            'installment' => $payload['installment'] ?? null,
            'installment_number' => $payload['installmentNumber'] ?? null,
            ...($extraMetadata ?? []),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $dueDate = isset($payload['dueDate'])
            ? CarbonImmutable::parse((string) $payload['dueDate'])->endOfDay()
            : null;

        return new GatewayPaymentDTO(
            externalId: (string) ($payload['id'] ?? ''),
            status: self::mapStatus(isset($payload['status']) ? (string) $payload['status'] : null),
            amount: number_format((float) ($payload['value'] ?? $payload['totalValue'] ?? 0), 2, '.', ''),
            expiresAt: $dueDate,
            metadata: $metadata !== [] ? $metadata : null,
        );
    }
}
