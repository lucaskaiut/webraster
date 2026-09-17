<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\DTOs\CreateGatewayPaymentDTO;
use App\Modules\Finance\DTOs\CreditCardDTO;
use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Exceptions\GatewayException;
use App\Modules\Finance\Models\FinanceBilling;
use App\Modules\Finance\Support\PaymentGatewayResolver;
use App\Modules\Shared\Subscription\Enums\BillingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BillingChargeService
{
    public function __construct(
        private readonly PaymentGatewayResolver $gateways,
        private readonly FinanceBillingService $billings,
    ) {}

    /**
     * @param  array<string, mixed>  $paymentData
     */
    public function charge(
        FinanceBilling $billing,
        PaymentMethod $method,
        array $paymentData = [],
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

        if (filled($billing->gateway_payment_id) && $billing->status === BillingStatus::AWAITING_PAYMENT) {
            return $billing->load(['client', 'subscription.plan']);
        }

        $gateway = $this->gateways->resolveReadyFor($method);

        $dueDate = CarbonImmutable::parse($billing->due_at)->startOfDay();
        $today = CarbonImmutable::today();

        try {
            $customer = $gateway->ensureCustomer($client);
            $payment = $gateway->createPayment(new CreateGatewayPaymentDTO(
                customerExternalId: $customer->externalId,
                amount: number_format($billing->totalCents() / 100, 2, '.', ''),
                paymentMethod: $method,
                dueDate: $dueDate->lessThan($today) ? $today : $dueDate,
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
            ],
        );

        return $billing->refresh()->load(['client', 'subscription.plan']);
    }
}
