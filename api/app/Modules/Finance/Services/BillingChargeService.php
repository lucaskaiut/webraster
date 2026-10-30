<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\DTOs\CreateGatewayPaymentDTO;
use App\Modules\Finance\DTOs\CreditCardDTO;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Exceptions\GatewayException;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Support\PaymentGatewayResolver;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use App\Modules\User\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BillingChargeService
{
    public function __construct(
        private readonly PaymentGatewayResolver $gateways,
        private readonly FinanceBillingService $billings,
        private readonly LateFeeService $lateFees,
    ) {}

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function charge(
        FinanceBilling $billing,
        PaymentMethod $method,
        array $paymentData = [],
        ?User $actor = null,
    ): FinanceBilling {
        if (! $billing->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['Esta cobrança não pode ser enviada ao gateway.'],
            ]);
        }

        $client = $billing->client;
        if ($client === null) {
            throw ValidationException::withMessages([
                'client_id' => ['Cliente da cobrança não encontrado.'],
            ]);
        }

        if ($this->canReuseGatewayCharge($billing, $method)) {
            return $billing->load(['client', 'subscription.plan']);
        }

        // Aplica multa/juros de atraso antes de emitir a nova cobrança.
        $this->lateFees->sync($billing, force: true);

        $gateway = $this->gateways->resolveReadyFor($method);

        // Cancela a cobrança anterior (expirada ou de outro método) para que a
        // fatura nunca tenha duas cobranças abertas no gateway.
        $this->billings->releaseGatewayPayment($billing, $actor);

        // A cobrança no gateway tem validade curta: o vencimento da fatura
        // é controlado pela plataforma (juros/multa), não pelo gateway.
        $expiresAt = CarbonImmutable::now()->addMinutes($this->gatewayExpiresMinutes());

        try {
            $customer = $gateway->ensureCustomer($client);
            $payment = $gateway->createPayment(new CreateGatewayPaymentDTO(
                customerExternalId: $customer->externalId,
                amount: number_format($billing->totalCents() / 100, 2, '.', ''),
                paymentMethod: $method,
                expiresAt: $expiresAt,
                externalReference: (string) $billing->uuid,
                description: $billing->description ?: $billing->code,
                creditCard: CreditCardDTO::tryFromArray($paymentData),
            ));
        } catch (GatewayException $exception) {
            throw ValidationException::withMessages([
                'payment_gateway' => [$exception->getMessage()],
            ]);
        }

        $from = $billing->status;
        $billing->payment_method = $method;
        $billing->payment_gateway = $gateway->key();
        $billing->status = BillingStatus::AWAITING_PAYMENT;
        $billing->gateway_payment_id = $payment->externalId !== '' ? $payment->externalId : null;
        $billing->gateway_expires_at = $expiresAt;
        $billing->invoice_url = $payment->invoiceUrl ?? $payment->bankSlipUrl;
        $billing->bank_slip_url = $payment->bankSlipUrl;
        $billing->pix_qr_code = $payment->pixQrcode;
        $billing->pix_copy_paste = $payment->pixCode;
        $billing->gateway_payload = $payment->metadata;
        $billing->save();

        $this->billings->recordEvent(
            $billing,
            'charged',
            $from,
            BillingStatus::AWAITING_PAYMENT,
            [
                'payment_method' => $method->value,
                'payment_gateway' => $gateway->key(),
                'gateway_payment_id' => $billing->gateway_payment_id,
                'gateway_expires_at' => $billing->gateway_expires_at?->toIso8601String(),
            ],
            $actor,
        );

        return $billing->refresh()->load(['client', 'subscription.plan']);
    }

    /**
     * A cobrança já emitida no gateway ainda vale para o método pedido?
     */
    private function canReuseGatewayCharge(FinanceBilling $billing, PaymentMethod $method): bool
    {
        return filled($billing->gateway_payment_id)
            && $billing->status === BillingStatus::AWAITING_PAYMENT
            && $billing->payment_method === $method
            && ! $this->gatewayChargeExpired($billing);
    }

    /**
     * A cobrança emitida no gateway expirou (ou nunca registrou validade)?
     */
    private function gatewayChargeExpired(FinanceBilling $billing): bool
    {
        return $billing->gateway_expires_at === null
            || $billing->gateway_expires_at->isPast();
    }

    private function gatewayExpiresMinutes(): int
    {
        return max(1, (int) config('finance.gateway_expires_minutes', 30));
    }
}
