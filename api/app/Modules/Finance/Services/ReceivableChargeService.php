<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Enums\PaymentMethod;
use App\Modules\Finance\Enums\ReceivableStatus;
use App\Modules\Finance\Gateways\Asaas\AsaasClient;
use App\Modules\Finance\Gateways\Asaas\AsaasException;
use App\Modules\Finance\Models\FinanceReceivable;
use Illuminate\Validation\ValidationException;

class ReceivableChargeService
{
    public function __construct(
        private readonly AsaasConfigService $asaasConfig,
        private readonly FinanceReceivableService $receivables,
    ) {}

    /**
     * @param  array<string, mixed>  $creditCardData
     */
    public function charge(
        FinanceReceivable $receivable,
        PaymentMethod $method,
        array $creditCardData = [],
    ): FinanceReceivable {
        if (! $receivable->status->isOpen()) {
            throw ValidationException::withMessages([
                'status' => ['Esta cobrança não pode ser enviada ao gateway.'],
            ]);
        }

        $config = $this->asaasConfig->get();

        if ($config === null || ! $config->is_active || blank($config->api_key)) {
            throw ValidationException::withMessages([
                'asaas' => ['Configuração Asaas não encontrada ou inativa para este tenant.'],
            ]);
        }

        $client = $receivable->client;
        if ($client === null) {
            throw ValidationException::withMessages([
                'client_id' => ['Cliente da cobrança não encontrado.'],
            ]);
        }

        try {
            $asaas = new AsaasClient($config);
            $customerId = $asaas->createOrUpdateCustomer($client);

            $payload = [
                'customer' => $customerId,
                'billingType' => $method->asaasBillingType(),
                'value' => round($receivable->totalCents() / 100, 2),
                'dueDate' => $receivable->due_at?->format('Y-m-d'),
                'description' => $receivable->description ?: $receivable->code,
                'externalReference' => $receivable->uuid,
            ];

            if ($method === PaymentMethod::CREDIT_CARD && $creditCardData !== []) {
                $payload['creditCard'] = $creditCardData['creditCard'] ?? $creditCardData;
                if (isset($creditCardData['creditCardHolderInfo'])) {
                    $payload['creditCardHolderInfo'] = $creditCardData['creditCardHolderInfo'];
                }
            }

            $payment = $asaas->createPayment($payload);
        } catch (AsaasException $exception) {
            throw ValidationException::withMessages([
                'asaas' => [$exception->getMessage()],
            ]);
        }

        $from = $receivable->status;
        $receivable->payment_method = $method;
        $receivable->status = ReceivableStatus::AWAITING_PAYMENT;
        $receivable->gateway_payment_id = isset($payment['id']) ? (string) $payment['id'] : null;
        $receivable->invoice_url = $payment['invoiceUrl'] ?? $payment['bankSlipUrl'] ?? null;
        $receivable->bank_slip_url = $payment['bankSlipUrl'] ?? null;
        $receivable->pix_qr_code = data_get($payment, 'pixQrCodeId')
            ? (string) data_get($payment, 'pixQrCodeId')
            : (data_get($payment, 'encodedImage') ? (string) data_get($payment, 'encodedImage') : null);
        $receivable->pix_copy_paste = data_get($payment, 'payload')
            ? (string) data_get($payment, 'payload')
            : null;
        $receivable->gateway_payload = $payment;
        $receivable->save();

        // PIX details may require a follow-up fetch
        if ($method === PaymentMethod::PIX && $receivable->gateway_payment_id) {
            try {
                $pix = $asaas->getPaymentPixQrCode($receivable->gateway_payment_id);
                $receivable->pix_qr_code = $pix['encodedImage'] ?? $receivable->pix_qr_code;
                $receivable->pix_copy_paste = $pix['payload'] ?? $receivable->pix_copy_paste;
                $receivable->save();
            } catch (\Throwable) {
                // ignore — payment already created
            }
        }

        $this->receivables->recordEvent(
            $receivable,
            'charged',
            $from,
            ReceivableStatus::AWAITING_PAYMENT,
            [
                'payment_method' => $method->value,
                'gateway_payment_id' => $receivable->gateway_payment_id,
            ],
        );

        return $receivable->refresh()->load(['client', 'contract.plan', 'subscription']);
    }
}
